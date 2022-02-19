<?php

namespace App\Command;

use App\Service\Pushbullet;
use App\Service\Wykop;
use App\Service\WykopException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CheckNotificationsCommand extends Command
{
    protected static $defaultName = 'furbot:check-notifications';
    private $testmode = false;

    private $params;
    private $wykop;
    private $pushbullet;

    public function __construct(
        ParameterBagInterface $params,
        Wykop                 $wykop,
        Pushbullet            $pushbullet,
        string                $name = null
    )
    {
        parent::__construct($name);

        $this->params = $params;
        $this->wykop = $wykop;
        $this->pushbullet = $pushbullet;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('test', InputArgument::OPTIONAL, 'Should I run in test mode?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Furbot: Calling daddy');

        // Check if test mode
        $this->testmode = !empty($input->getArgument('test'));
        if ($this->testmode) {
            $io->warning('Running in test mode');
        }

        // Login to Wykop
        if (!$this->wykop->login()) {
            $io->error('Unable to get user key from Wykop!');
            return Command::FAILURE;
        }

        // Ask Wykop for notifications
        try {
            $notifications = $this->wykop->getNotifications();
            if (empty($notifications->data)) {
                $io->success('No new notifications!');
                return Command::SUCCESS;
            }

            $sent = 0;

            // Send the notifications to my master
            foreach ($notifications->data as $notify) {

                // If notification is unread, send a Pushbullet notification
                if ($notify->new) {
                    $this->pushbullet->push('[furbot] Powiadomienie z Wykopu', $notify->body, $notify->url);
                    $sent++;
                }
            }

            $io->success('Sent ' . $sent . ' new notifications!');
            return Command::SUCCESS;

            
        } catch (WykopException $exception) {
            $this->pushbullet->push(
                '[furbot] Błąd API Wykopu',
                $exception->getMessage() . "\n\n" . $exception->getFile() . ':' . $exception->getLine()
            );

            return Command::FAILURE;
        }
    }
}