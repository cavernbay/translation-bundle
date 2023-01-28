<?php

declare(strict_types=1);

namespace CavernBay\TranslationBundle\Factory;

use CavernBay\TranslationBundle\Model\ImportSettingsModel;
use Symfony\Component\Console\Input\InputInterface;

class ImportSettingsModelFactory
{
    public function createFromConsoleInput(InputInterface $input): ImportSettingsModel
    {
        $model = new ImportSettingsModel();
        $model->setLocales(explode(',', $input->getArgument('locales')));
        $model->setCsv($input->getArgument('csv'));
        $model->setBundlesNames(explode(',', $input->getOption('bundles')));
        $model->setOverwriteExisting($input->hasOption('overwrite-existing'));
        $model->setDomains(explode(',', $input->getOption('domains')));
        $model->setDomains(explode(',', $input->getOption('domains')));
        $model->setSeparator($input->getOption('separator'));

        return $model;
    }
}
