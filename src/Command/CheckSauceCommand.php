<?php

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

class CheckSauceCommand extends Command
{
    protected static $defaultName = 'furbot:check-sauce';
    private $testmode = false;

    private $params;
    private $wykop;
    private $saucenao;
    private $e621;
    private $postRepo;
    private $em;
    private $entryHelper;
    private $removeArtists;

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
    }

    protected function configure(): void
    {
        $this
            ->addArgument('test', InputArgument::OPTIONAL, 'Should I run in test mode?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Furbot: Getting saucy');

        // Check if test mode
        $this->testmode = !empty($input->getArgument('test'));
        if ($this->testmode) {
            $io->warning('Running in test mode');
        }

        // Login to Wykop
        if (!$this->wykop->login()) {
            $io->error('Unable to get user key from Wykop!');
            return Command::FAILURE;
        } else {
            $io->success('Logged in to Wykop!');
        }

        $io->info('Getting and checking posts');
        $entries = $this->wykop->getTag('furry');

        foreach ($entries->data as $entry) {

            // Skip my own posts
            if ($entry->author->login === $this->params->get('wykop.login')) {
                continue;
            }

            // Display info about the post for debug purposes
            $io->section('Entry info');
            $io->definitionList(
                ['ID' => $entry->id],
                ['Author' => $entry->author->login],
                ['URL' => 'https://wykop.pl/wpis/' . $entry->id],
                ['Type' => $entry->embed->type],
                ['Embed' => $entry->embed->url],
                ['+18' => $entry->embed->plus18 ? 'Yes' : 'No']
            );

            // Skip posts with no media
            if (empty($entry->embed)) {
                $io->warning('No media, skipping');
                continue;
            }

            // Check if entry already exists in database
            $post = $this->postRepo->findOneBy(['entryId' => $entry->id]);
            if (!$this->testmode && $post) {
                // It exists, means I've seen it, so should leave it in this process
                $io->warning('Already checked at ' . $post->getTime()->format('Y-m-d H:i:s'));
                continue;
            }

            switch ($entry->embed->type) {
                case 'image':
                    // Prepare post for the database
                    $post = new Post();
                    $post->setEntryId($entry->id);
                    $post->setAuthor($entry->author->login);
                    $post->setEmbed($entry->embed->url);
                    $post->setStatus(Post::STATUS_CHECKED);
                    $post->setTime(new DateTime("now"));

                    // Let's extract tags - needed later for the message
                    $entry->tags = [];

                    $body = strip_tags($entry->body);
                    preg_match_all("/(#\w+)/u", $body, $matches);

                    if ($matches) {
                        $hashtagsArray = array_count_values($matches[0]);
                        $entry->tags = array_keys($hashtagsArray);
                    }

                    // Declare variables
                    $message = '';
                    $sauces = [];
                    $artists = [];
                    $sources = [];

                    // Get sources
                    try {
                        $sources = $this->saucenao->getSauce($entry->embed->url);
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
                        $comment = new MirkoComment($entry->id, $message);
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

                    // #furry info
                    $hashtags = explode(' ', $this->params->get('furry_hashtags'));
                    $hashtags = array_merge($entry->tags, $hashtags);
                    $hashtags = array_unique($hashtags);
                    $lastTag = array_pop($hashtags);

                    $disclaimer =
                        'Nie chcesz widzieć takich postów? Dodaj ' . implode(' ', $hashtags) . ' i ' . $lastTag . ' na #czarnolisto' . "\n" .
                        '!Beep boop - jestem botem. Dobrego dzionka! (✌ ﾟ ∀ ﾟ)☞';

                    $io->text(explode("\n", $disclaimer));

                    if ($this->testmode) {
                        $io->warning('Test mode, not adding the disclaimer');
                    } else {
                        $comment = new MirkoComment($entry->id, $disclaimer);
                        $this->wykop->addComment($comment);
                    }

                    // Save info to the db
                    if (!$this->testmode) {
                        $post->setMessage($message);
                        $this->em->persist($post);
                        $this->em->flush();
                    }
                    break;

                default:
                    $io->error('I don\'t know how to deal with ' . $entry->embed->type . ' embed');
            }
        }

        return Command::SUCCESS;
    }
}