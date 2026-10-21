<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $writer = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);
        $tabList = $config->get('tabList') ?? [];

        foreach (['SmShift', 'SmModels', 'SmOperators', 'SmShiftFinancialSnapshot', 'SmRegistrationLink', 'SmAnalytics', 'SmCredits'] as $tab) {
            if (!in_array($tab, $tabList, true)) {
                $tabList[] = $tab;
            }
        }

        $writer->set('tabList', $tabList);
        $writer->set('applicationName', 'onlineCRM');
        $writer->save();
        $container->getByClass(InjectableFactory::class)
            ->create(\Espo\Modules\StudioManagement\Tools\StaffMigration::class)
            ->run();
        $container->getByClass(InjectableFactory::class)
            ->create(\Espo\Modules\StudioManagement\Tools\RegistrationLinkMigration::class)
            ->run();
    }
}
