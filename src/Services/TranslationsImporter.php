<?php

declare(strict_types=1);

namespace CavernBay\TranslationBundle\Services;

use CavernBay\TranslationBundle\Components\CsvLoader;
use CavernBay\TranslationBundle\Model\ImportSettingsModel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Dumper;

class TranslationsImporter
{
    public function __construct(
        private KernelInterface $kernel,
        private LoadTranslationService $loadTranslationService,
        private Filesystem $filesystem,
        private Dumper $dumper,
    ) {
    }

    public function import(ImportSettingsModel $importSettings): iterable
    {
        $importTranslations = CsvLoader::load(
            $importSettings->getCsv(),
            $importSettings->getBundlesNames(),
            $importSettings->getDomains(),
            $importSettings->getLocales(),
            $importSettings->getSeparator()
        );

        // load existing translations on working bundles
        $bundles = $this->loadBundles($importTranslations);

        $this->loadTranslationService->loadBundlesTranslationFiles(
            $bundles,
            $importSettings->getLocales(),
            $importSettings->getDomains()
        );

        $allTranslations = $importTranslations;
        // merge translations if we do not overwrite the data
        if (!$importSettings->isOverwriteExisting()) {
            $allTranslations = array_replace_recursive($this->loadTranslationService->getTranslations(), $importTranslations);
        }

        return $this->rewriteFiles($allTranslations, $importSettings->getLocales(), $bundles);
    }

    private function rewriteFiles($allTranslations, $locales, $bundles): iterable
    {
        // rewrite files (Bundle/domain.locale.yml)
        foreach ($allTranslations as $bundleName => $bundleTranslations) {
            foreach ($bundleTranslations as $domain => $domainTranslations) {
                ksort($domainTranslations);

                foreach ($locales as $locale) {
                    $localTranslations = $this->prepareLocaleTranslations($domainTranslations, $locale);

                    $filePath = $this->getDestinationFilePath($bundleName, $bundles, $domain, $locale);

                    $ymlContent = $this->dumper->dump($localTranslations, 10);

                    $wasFileChanged = $this->saveFile($filePath, $ymlContent);
                    if ($wasFileChanged) {
                        yield $filePath;
                    }
                }
            }
        }
    }

    private function prepareLocaleTranslations($domainTranslations, $locale): array
    {
        $localeTranslations = [];
        foreach ($domainTranslations as $key => $localeTranslation) {
            if (isset($localeTranslation[$locale])) {
                $this->assignArrayByPath($localeTranslations, $key, $localeTranslation[$locale]);
            }
        }

        return $localeTranslations;
    }

    private function saveFile(string $filePath, string $content): bool
    {
        $originalSha1 = null;
        if (file_exists($filePath)) {
            $originalSha1 = sha1_file($filePath);
        }
        file_put_contents($filePath, $content);
        $newSha1 = sha1_file($filePath);

        return $newSha1 !== $originalSha1;
    }

    private function getDestinationFilePath(string $bundleName, array $bundles, string $domain, string $locale): string
    {
        if ('app' === $bundleName) {
            $basePath = $this->loadTranslationService->getAppTranslationsPath();
        } else {
            /** @var BundleInterface $bundle */
            $bundle = $bundles[$bundleName];
            $basePath = $bundle->getPath().'/translations';
            if (!$this->filesystem->exists($basePath)) {
                // Symfony does not recommend storing translations in Resources folder but let's check due compatibility
                $basePath = $bundle->getPath().'/Resources/translations';
            }
            if (!$this->filesystem->exists($basePath)) {
                $basePath = $bundle->getPath().'/translations';
            }
        }
        $filePath = sprintf('%s%s%s.%s.%s', $basePath, DIRECTORY_SEPARATOR, $domain, $locale, '.yaml');
        if (!$this->filesystem->exists($filePath)) {
            // backwards compatibility with existing .yml files (as .yaml ir recommended)
            $filePath = sprintf('%s%s%s.%s.%s', $basePath, DIRECTORY_SEPARATOR, $domain, $locale, '.yml');
        }
        if (!$this->filesystem->exists($filePath)) {
            $filePath = sprintf('%s%s%s.%s.%s', $basePath, DIRECTORY_SEPARATOR, $domain, $locale, '.yaml');
        }
        if (!$this->filesystem->exists($basePath)) {
            $this->filesystem->mkdir($basePath);
        }

        return $filePath;
    }

    private function loadBundles($importTranslations): array
    {
        $bundles = [];

        foreach (array_keys($importTranslations) as $bundleName) {
            if ('app' === $bundleName) {
                $bundles['app'] = 'app';

                continue;
            }
            $bundle = $this->kernel->getBundle($bundleName);
            $bundles[$bundleName] = $bundle;
        }

        return $bundles;
    }

    /**
     * @param array  $arr
     * @param string $path
     * @param string $value
     * @param string $delimiter
     * @param string $escape
     */
    public function assignArrayByPath(&$arr, $path, $value, $delimiter = '.', $escape = '\\'): void
    {
        $keys = explode($delimiter, $path);

        foreach ($keys as $key) {
            $arr = &$arr[$key];
        }

        $arr = $value;
    }
}
