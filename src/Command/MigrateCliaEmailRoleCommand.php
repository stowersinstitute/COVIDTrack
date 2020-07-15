<?php

namespace App\Command;

use App\Entity\AppUser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Usage:
 *
 *     $ bin/console app:migrate:clia-email-role -v
 */
class MigrateCliaEmailRoleCommand extends BaseAppCommand
{
    protected static $defaultName = 'app:migrate:clia-email-role';

    protected function configure()
    {
        $this
            ->setDescription('Migrates user roles for sending CLIA email notification about test results')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $old = 'ROLE_NOTIFY_GROUP_RECOMMENDED_TESTING';
        $new = 'ROLE_NOTIFY_ABOUT_VIRAL_RESULTS';

        foreach ($this->getAllUsers() as $user) {
            if (!$user->hasRoleExplicit($old)) continue;

            $this->outputDebug(sprintf('Updating roles for user %s', $user->getDisplayName()));

            $user->addRole($new);
            $user->removeRole($old);
        }

        return 0;
    }

    /**
     * @return AppUser[]
     */
    private function getAllUsers(): array
    {
        return $this->em->getRepository(AppUser::class)->findAll();
    }
}
