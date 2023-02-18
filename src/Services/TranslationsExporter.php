<?php

declare(strict_types=1);

namespace CavernBay\TranslationBundle\Services;

use CavernBay\TranslationBundle\Model\ExportSettingsModel;
use League\Csv\Writer;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;

class TranslationsExporter
{
    public const APP_BUNDLE_NAME = 'app';

    public function __construct(
        private LoadTranslationService $loadTranslationService,
        private ReporterService $reporterService,
        private KernelInterface $kernel,
    ) {
    }

    public function export(ExportSettingsModel $exportSettingsModel): void
    {
        $bundlesNames = $exportSettingsModel->getBundles();

        $files = $this->loadBundlesTranslations($bundlesNames, $exportSettingsModel);
        $this->exportTranslationsToFile($exportSettingsModel, $files);
    }

    protected function loadBundlesTranslations(array $bundles, ExportSettingsModel $exportSettingsModel): Finder
    {
        $finder = new Finder();
        foreach ($bundles as $bundle) {
            // fix symfony 4 applications (use magic bundle name "app")
            if (static::APP_BUNDLE_NAME === $bundle) {
                $files = $this->loadAppTranslations($exportSettingsModel);
                $finder->append($files);
                continue;
            }

            if ('all' === $bundle) {
                $files = $this->loadAppTranslations($exportSettingsModel);
                $finder->append($files);
                $files = $this->loadBundlesTranslations($this->kernel->getBundles(), $exportSettingsModel);
                $finder->append($files);
                continue;
            }

            $files = $this->loadBundleTranslations($bundle, $exportSettingsModel);
            $finder->append($files);
        }

        return $finder;
    }

    protected function loadBundleTranslations($bundle, ExportSettingsModel $exportSettingsModel): Finder
    {
        $finder = new Finder();
        if (is_string($bundle)) {
            $bundle = $this->kernel->getBundle($bundle);
        }

        if (method_exists($bundle, 'getParent') && null !== $bundle->getParent()) {
            $bundles = $this->kernel->getBundle($bundle->getParent(), false);
            $bundle = $bundles[1];
            $this->reporterService->report(sprintf(
                'Using: %s as bundle to lookup translations files for.',
                $bundle->getName()
            ));
        }

        // locales to export
        $files = $this->loadTranslationService->loadBundleTranslationFiles(
            $bundle,
            $exportSettingsModel->getLocales(),
            $exportSettingsModel->getDomains()
        );
        if ($files !== null) {
            $finder->append($files);
        }
        // locale reference
        $files = $this->loadTranslationService->loadBundleTranslationFiles(
            $bundle,
            [$exportSettingsModel->getLocale()],
            $exportSettingsModel->getDomains()
        );
        if ($files !== null) {
            $finder->append($files);
        }

        return $finder;
    }

    protected function loadAppTranslations(ExportSettingsModel $exportSettingsModel): Finder
    {
        $finder = new Finder();
        $files = $this->loadTranslationService->loadAppTranslationFiles(
            $exportSettingsModel->getLocales(),
            $exportSettingsModel->getDomains()
        );
        $finder->append($files);
        // locale reference
        $files = $this->loadTranslationService->loadAppTranslationFiles(
            [$exportSettingsModel->getLocale()],
            $exportSettingsModel->getDomains()
        );
        $finder->append($files);

        return $finder;
    }

    protected function exportTranslationsToFile(ExportSettingsModel $exportSettingsModel, Finder $files): void
    {
        $locales = [];
        if ($exportSettingsModel->getLocales() === ['all']) {
            try {
                foreach ($files as $file) {
                    [, $locale] = explode('.', $file->getFilename());
                    $locales[] = $locale;
                }
            } catch (\LogicException) {}
        } else {
            $locales = $exportSettingsModel->getLocales();
        }

        $locales = [$exportSettingsModel->getLocale(), ...array_diff($locales, [$exportSettingsModel->getLocale()])];

        $writer = Writer::createFromPath($exportSettingsModel->getFileName(), 'w+');
        if ($exportSettingsModel->isIncludeUTF8Bom()) {
            // $writer->setOutputBOM(Writer::BOM_UTF8); is only supported when doing $writer->output and (string)
            // have to do a hack for now since there is no "nice" way so far
            (fn () => $this->document->fwrite(Writer::BOM_UTF8))->call($writer);

        }
        $writer->setDelimiter($exportSettingsModel->getSeparator());

        $columns = ['Bundle', 'Domain', 'Key', ...$locales];

        $writer->insertOne($columns);

        foreach ($this->loadTranslationService->getTranslations() as $bundleName => $domains) {
            foreach ($domains as $domain => $translations) {
                foreach ($translations as $trKey => $trLocales) {
                    if ($this->shouldExportRow($trLocales, $locales, $exportSettingsModel->isOnlyMissing())) {
                        $translatedLocales = array_map(fn ($locale) => $trLocales[$locale] ?? '', $locales);
                        $row = [$bundleName, $domain, $trKey, ...$translatedLocales];
                        $writer->insertOne($row);
                    }
                }
            }
        }
    }

    protected function shouldExportRow(array $translations, array $locales, bool $isMissingOnly): bool
    {
        if (!$isMissingOnly) {
            return true;
        }

        // checks if at least one entry from $locales is missing in $translations,
        // returns true in that case, else otherwise
        return array_reduce($locales, fn ($prev, $locale) => $prev || !isset($translations[$locale]), false);
    }
}
