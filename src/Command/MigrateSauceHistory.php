<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Entity\Post;
use App\Repository\PostRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateSauceHistory extends Command
{
    protected static $defaultName = 'furbot:migrate-history';

    private $em;
    private $postRepo;

    public function __construct(
        EntityManagerInterface $em,
        PostRepository $postRepo,
        string $name = null
    )
    {
        parent::__construct($name);

        $this->em = $em;
        $this->postRepo = $postRepo;
    }

    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Furbot: Migrating sauce history');

        $history = json_decode(file_get_contents(__DIR__ . '/../../post_sauce.json'), true);
        $io->progressStart(count($history));
        foreach ($history as $item) {
            // Ignore old ones
            if ($item['status'] == 'old') continue;

            $post = $this->postRepo->findOneBy(['entryId' => $item['id']]);

            if (!$post) {
                $post = new Post();
            }

            $post->setEntryId($item['id']);
            $post->setStatus($item['status']);
            $post->setSauces($item['sauces']);
            $post->setRawSauces($item['snao']);
            $post->setMessage($item['message'] ?? '');
            $post->setTime((new DateTime())->setTimestamp($item['time']));
            if (!empty($item['entry'])) {
                $post->setAuthor($item['entry']['author']);
                $post->setEmbed($item['entry']['embed']);
            } else {
                $post->setAuthor('');
                $post->setEmbed('');
            }

            $this->em->persist($post);
            $this->em->flush();

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('Done');

        return Command::SUCCESS;
    }
}