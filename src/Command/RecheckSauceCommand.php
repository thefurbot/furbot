<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Entity\MirkoComment;
use App\Entity\Post;
use App\Repository\PostRepository;
use App\Service\E621;
use App\Service\EntryHelper;
use App\Service\Saucenao;
use App\Service\Wykop;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RecheckSauceCommand extends Command
{
    protected static $defaultName = 'furbot:recheck-sauce';
    private $testmode = false;

    private $params;
    private $wykop;
    private $saucenao;
    private $e621;
    private $postRepo;
    private $em;
    private $entryHelper;
    private $removeArtists;
    private $recheckNewerThan;

    public function __construct(
        ParameterBagInterface  $params,
        Wykop                  $wykop,
        Saucenao               $saucenao,
        E621                   $e621,
        PostRepository         $postRepo,
        EntityManagerInterface $em,
        EntryHelper            $entryHelper,
        string                 $name = null
    )
    {
        parent::__construct($name);

        $this->params = $params;
        $this->wykop = $wykop;
        $this->saucenao = $saucenao;
        $this->e621 = $e621;
        $this->postRepo = $postRepo;
        $this->em = $em;
        $this->entryHelper = $entryHelper;

        $this->removeArtists = explode(' ', $this->params->get('remove_artists'));
        $this->recheckNewerThan = $this->params->get('recheck_posts') * 86400;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('test', InputArgument::OPTIONAL, 'Should I run in test mode?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Furbot: I am a dwarf and I\'m digging a sauce');

        // Check if test mode
        $this->testmode = !empty($input->getArgument('test'));
        if ($this->testmode) {
            $io->warning('Running in test mode');
        }

        // Find posts to rescan
        $rescanPostsNewerThan = time() - $this->recheckNewerThan;
        $startDateTime = new DateTime();
        $startDateTime->setTimestamp($rescanPostsNewerThan);
        $postsToRescan = $this->postRepo->findPostsToRecheck($startDateTime);

        $io->info('Found ' . count($postsToRescan) . ' posts to re-check');

        // Login to Wykop
        if (!$this->wykop->login()) {
            $io->error('Unable to get user key from Wykop!');
            return Command::FAILURE;
        } else {
            $io->success('Logged in to Wykop!');
        }


        foreach ($postsToRescan as $post) {

            // Display info about the post for debug purposes
            $io->section('Entry info');
            $io->definitionList(
                ['ID' => $post->getEntryId()],
                ['Author' => $post->getAuthor()],
                ['URL' => 'https://wykop.pl/wpis/' . $post->getEntryId()],
                ['Embed' => $post->getEmbed()]
            );

            // Declare variables
            $message = '';
            $sauces = [];
            $artists = [];
            $sources = [];

            // Get sources
            try {
                $sources = $this->saucenao->getSauce($post->getEmbed());
            } catch (Exception $e) {
                $io->error('[Saucenao] ' . $e->getMessage());
            }

            // Get sauce links and artists
            [$sauces, $artists] = $this->entryHelper->convertSources($this->e621, $sources);
            $post->setSauces($sauces);
            $post->setRawSauces($sources);

            // If no valid sauces were found, save that info to the db and continue to the next post
            if (empty($sauces)) {
                $post->setStatus(Post::STATUS_NO_VALID_SAUCES);
                $this->em->persist($post);
                $this->em->flush();
                continue;
            }

            // Clean artists
            foreach ($this->removeArtists as $removeArtist) {
                if (($key = array_search($removeArtist, $artists)) !== false) {
                    unset($artists[$key]);
                }
            }

            // Replace _ with spaces, remove the "(artist)" suffix, capitalize words
            foreach ($artists as &$artist) {
                $artist = str_replace('_', ' ', $artist);
                $artist = str_replace(' (artist)', '', $artist);
                $artist = ucwords($artist);
            }

            // Remove duplicates
            $artists = array_unique($artists);

            // Construct the message
            $message = (!empty($artists) ? 'Artysta: ' . implode(', ', $artists) . "\n" : '') .
                (count($sauces) == 1 ? 'Źródło:' : 'Źródła:');
            foreach ($sauces as $s) {
                $message .= ' [' . $s['label'] . '](' . $s['url'] . ') |';
            }
            $message = substr($message, 0, -2);

            // Show message for the debug
            $io->text(explode("\n", $message));

            // Send message to Wykop
            if ($this->testmode) {
                $io->warning('Test mode, not adding the comment');
            } else {
                $comment = new MirkoComment($post->getEntryId(), $message);
                $result = $this->wykop->addComment($comment);

                // Set post status
                if ($result === false) {
                    $io->error('Unable to add comment on Wykop');
                    $post->setStatus(Post::STATUS_WYKOP_ERROR);
                } else {
                    $io->success('Added comment to Wykop');
                    $post->setStatus(Post::STATUS_COMMENTED);
                }
            }

            // Save info to the db
            if (!$this->testmode) {
                $post->setMessage($message);
                $this->em->persist($post);
                $this->em->flush();
            }
        }

        return Command::SUCCESS;
    }
}