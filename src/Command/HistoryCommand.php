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

class HistoryCommand extends Command
{
    protected static $defaultName = 'furbot:history';
    private $testmode = false;

    private $params;
    private $postRepo;

    public function __construct(
        PostRepository $postRepo,
        string         $name = null
    )
    {
        parent::__construct($name);

        $this->postRepo = $postRepo;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Wykop entry ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Furbot: Digging the past');

        $id = $input->getArgument('id');
        $post = $this->postRepo->findOneBy(['entryId' => $id]);

        if (!$post) {
            $io->error('This post is not present in history DB');
            return Command::FAILURE;
        }

        $status = 'Unknown';
        switch ($post->getStatus()) {
            case Post::STATUS_CHECKED:
                $status = 'Checked';
                break;

            case Post::STATUS_COMMENTED:
                $status = 'Commented';
                break;

            case Post::STATUS_NO_VALID_SAUCES:
                $status = 'No valid sources found';
                break;

            case Post::STATUS_WYKOP_ERROR:
                $status = 'Wykop error';
                break;
        }

        $io->section('Post info');
        $io->definitionList(
            ['ID' => $post->getEntryId()],
            ['Author' => $post->getAuthor()],
            ['URL' => 'https://wykop.pl/wpis/' . $post->getEntryId()],
            ['Embed' => $post->getEmbed()],
            ['Status' => $status]
        );

        $io->section('Sauces');
        dump($post->getSauces());

        $io->section('Raw Saucenao results');
        dump($post->getRawSauces());

        return Command::SUCCESS;
    }
}