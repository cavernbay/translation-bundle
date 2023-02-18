<?php

declare(strict_types=1);

namespace CavernBay\TranslationBundle\Factory;

use CavernBay\TranslationBundle\Model\ExportSettingsModel;
use Symfony\Component\Console\Input\InputInterface;

class ExportSettingsModelFactory
{
    public function createFromConsoleInput(InputInterface $input): ExportSettingsModel
    {
        $model = new ExportSettingsModel();
        $model->setBundles(explode(',', $input->getArgument('bundles')));
        $model->setLocale($input->getArgument('locale'));
        $model->setLocales(explode(',', $input->getArgument('locales')));
        $model->setDomains(explode(',', $input->getOption('domains')));
        $model->setSeparator($input->getOption('separator'));
        $model->setOnlyMissing((bool) $input->getOption('only-missing'));
        $model->setIncludeUTF8Bom((bool) $input->getOption('include-bom'));
        $model->setFileName($input->getArgument('csv'));

        return $model;
    }

    public function createForAllBundles(string $fileName, string $separator): ExportSettingsModel
    {
        $model = new ExportSettingsModel();
        $model->setBundles(['all']);
        $model->setLocale('en'); // TODO: use default configured locale if possible
        $model->setLocales(['all']);
        $model->setDomains(['all']);
        $model->setSeparator($separator);
        $model->setOnlyMissing(false);
        $model->setIncludeUTF8Bom(true);
        $model->setFileName($fileName);

        return $model;
    }
}
