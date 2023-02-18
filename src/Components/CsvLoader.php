<?php

namespace CavernBay\TranslationBundle\Components;

use CavernBay\TranslationBundle\Exception\DataException;
use League\Csv\Reader;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Translation\Loader\FileLoader;

/**
 * Class CsvLoader
 */
class CsvLoader
{

    /**
     * Load CSV File.
     *
     * @param        $filepath
     * @param array  $bundles bundles names to load
     * @param array  $domains domains to load
     * @param array  $locales locales to load
     * @param string $separator
     *
     * @return array
     * @throws \Exception
     *
     */
    public static function load($filepath, $bundles, $domains, $locales, $separator = "\t"): array
    {
        if (!file_exists($filepath)) {
            throw new FileNotFoundException(sprintf('File "%s" not found.', $filepath));
        }

        $reader = Reader::createFromPath($filepath);
        $reader->setHeaderOffset(0);
        $reader->setDelimiter($separator);

        if (false === $reader) {
            throw new IOException('Error loading "%s".', $filepath);
        }

        $localesKeys = [];
        $columnsKeys = $reader->getHeader();

        foreach (['Bundle', 'Domain', 'Key'] as $mandatoryColumn) {
            if (!in_array($mandatoryColumn, $columnsKeys)) {
                throw new DataException('mandatory column '.$mandatoryColumn.' is missing');
            }
        }
        foreach ($locales as $locale) {
            $localeKey = array_search($locale, $columnsKeys);
            if ($localeKey === false) {
                throw new DataException('locale column '.$locale.' is missing');
            }
            // keep column id
            $localesKeys[$locale] = $localeKey;
        }

        $translations = [];

        foreach ($reader->getIterator() as $lineId => $row) {
            $bundleName = $row['Bundle'];
            $domainName = $row['Domain'];
            if (in_array($bundleName, $bundles) || count($bundles) == 1 && $bundles[0] == 'all') {
                if (in_array($domainName, $domains) || count($domains) == 1 && $domains[0] == 'all') {
                    foreach ($locales as $locale) {
                        if (!isset($row[$locale])) {
                            throw new DataException('missing column value on line '.$lineId.', column '.$localesKeys[$locale]);
                        }
                        $value = str_replace('\n', "\n", $row[$locale]);
                        if ($value) {
                            // bundle / domain / key
                            $translations[$bundleName][$domainName][$row['Key']][$locale] = $value;
                        }
                    }
                }
            }
        }

        return $translations;
    }
}
