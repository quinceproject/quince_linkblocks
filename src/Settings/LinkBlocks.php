<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\LinkBlocks\Settings;

use Category;
use CMS;
use Configuration;
use Context;
use Db;
use Quince\Engine\Ui\CategoryChoices;
use Language;

/**
 * Bloki odnośników: odczyt, zapis i przygotowanie danych dla stopki.
 *
 * Trzy tabele, bo trzy różne rzeczy: blok (gdzie stoi i czy działa), jego nazwa
 * w każdym języku, jego odnośniki. Odnośnik do kategorii i do strony treści
 * trzyma sam numer: nazwę i adres bierzemy przy wyświetlaniu, więc zmiana
 * nazwy w katalogu jest w stopce widoczna od razu.
 *
 * Własny adres jest wielojęzyczny, bo ta sama pozycja bywa inną stroną w innym
 * języku; kategoria i strona treści nie, bo PrestaShop rozwiązuje ich adresy sam.
 */
final class LinkBlocks
{
    /** Odnośnik prowadzi do kategorii, strony treści albo pod wpisany adres. */
    public const TYPES = ['category', 'cms', 'custom'];

    public function installTables(): bool
    {
        $db = Db::getInstance();
        $silnik = _MYSQL_ENGINE_;

        $sql = [];

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'quince_linkblock` (
            `id_quince_linkblock` INT(11) NOT NULL AUTO_INCREMENT,
            `hook` VARCHAR(128) NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `show_name` TINYINT(1) NOT NULL DEFAULT 1,
            `position` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_quince_linkblock`),
            KEY `hook` (`hook`)
        ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4;';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'quince_linkblock_lang` (
            `id_quince_linkblock` INT(11) NOT NULL,
            `id_lang` INT(11) NOT NULL,
            `name` VARCHAR(255) NOT NULL DEFAULT "",
            PRIMARY KEY (`id_quince_linkblock`, `id_lang`)
        ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4;';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'quince_linkblock_link` (
            `id_quince_linkblock_link` INT(11) NOT NULL AUTO_INCREMENT,
            `id_quince_linkblock` INT(11) NOT NULL,
            `type` VARCHAR(16) NOT NULL DEFAULT "custom",
            `id_category` INT(11) NOT NULL DEFAULT 0,
            `id_cms` INT(11) NOT NULL DEFAULT 0,
            `new_tab` TINYINT(1) NOT NULL DEFAULT 0,
            `position` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_quince_linkblock_link`),
            KEY `id_quince_linkblock` (`id_quince_linkblock`)
        ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4;';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'quince_linkblock_link_lang` (
            `id_quince_linkblock_link` INT(11) NOT NULL,
            `id_lang` INT(11) NOT NULL,
            `label` VARCHAR(255) NOT NULL DEFAULT "",
            `url` VARCHAR(512) NOT NULL DEFAULT "",
            PRIMARY KEY (`id_quince_linkblock_link`, `id_lang`)
        ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4;';

        foreach ($sql as $zapytanie) {
            if (!$db->execute($zapytanie)) {
                return false;
            }
        }

        return true;
    }

    public function dropTables(): bool
    {
        $db = Db::getInstance();

        foreach (['quince_linkblock_link_lang', 'quince_linkblock_link',
                  'quince_linkblock_lang', 'quince_linkblock'] as $tabela) {
            if (!$db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $tabela . '`')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hooki, w których stoją dziś bloki. Moduł rejestruje się dokładnie na nie.
     *
     * @return string[]
     */
    public function hooksInUse(): array
    {
        $wiersze = Db::getInstance()->executeS(
            'SELECT DISTINCT hook FROM `' . _DB_PREFIX_ . 'quince_linkblock` WHERE hook != ""'
        ) ?: [];

        $out = array_map(static fn (array $w): string => (string) $w['hook'], $wiersze);

        // Wspólny hook stopki jako punkt wyjścia: sklep, który nie ustawił jeszcze
        // żadnej kolumny, ma gdzie zobaczyć pierwszy blok.
        if (!in_array('displayFooter', $out, true)) {
            $out[] = 'displayFooter';
        }

        return $out;
    }

    /**
     * Wszystkie bloki do panelu, z nazwami w każdym języku i liczbą odnośników.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $bloki = Db::getInstance()->executeS(
            'SELECT b.*, (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'quince_linkblock_link` l'
            . ' WHERE l.id_quince_linkblock = b.id_quince_linkblock) AS links'
            . ' FROM `' . _DB_PREFIX_ . 'quince_linkblock` b ORDER BY b.position, b.id_quince_linkblock'
        ) ?: [];

        $out = [];

        foreach ($bloki as $blok) {
            $id = (int) $blok['id_quince_linkblock'];

            $out[] = [
                'id' => $id,
                'hook' => (string) $blok['hook'],
                'active' => (bool) $blok['active'],
                'show_name' => (bool) $blok['show_name'],
                'position' => (int) $blok['position'],
                'links_count' => (int) $blok['links'],
                'names' => $this->names($id),
                'links' => $this->links($id),
            ];
        }

        return $out;
    }

    /**
     * Bloki jednego hooka, rozwiązane dla języka: to, co dostaje stopka.
     *
     * Odnośnik bez adresu i bez nazwy wypada tutaj, a nie w szablonie: pusta
     * pozycja w stopce to wada, nie decyzja sklepu.
     *
     * @return array<int,array{name:string,show_name:bool,links:array<int,array{label:string,url:string,new_tab:bool}>}>
     */
    public function forHook(string $hook, int $idLang): array
    {
        $bloki = Db::getInstance()->executeS(
            'SELECT id_quince_linkblock, show_name FROM `' . _DB_PREFIX_ . 'quince_linkblock`'
            . ' WHERE active = 1 AND hook = "' . pSQL($hook) . '" ORDER BY position, id_quince_linkblock'
        ) ?: [];

        $out = [];

        foreach ($bloki as $blok) {
            $id = (int) $blok['id_quince_linkblock'];
            $odnosniki = [];

            foreach ($this->links($id) as $link) {
                $gotowy = $this->resolve($link, $idLang);

                if ($gotowy !== null) {
                    $odnosniki[] = $gotowy;
                }
            }

            if ($odnosniki === []) {
                continue;
            }

            $nazwy = $this->names($id);

            $out[] = [
                'name' => (string) ($nazwy[$idLang] ?? ''),
                'show_name' => (bool) $blok['show_name'],
                'links' => $odnosniki,
            ];
        }

        return $out;
    }

    /**
     * Odnośnik gotowy do wyświetlenia albo null, jeśli nie ma czego pokazać.
     *
     * @param array<string,mixed> $link
     *
     * @return array{label:string,url:string,new_tab:bool}|null
     */
    private function resolve(array $link, int $idLang): ?array
    {
        $wlasnaNazwa = trim((string) ($link['labels'][$idLang] ?? ''));

        switch ($link['type']) {
            case 'category':
                $kategoria = new Category((int) $link['id_category'], $idLang);

                if (!$kategoria->id || !$kategoria->active) {
                    return null;
                }

                return [
                    'label' => $wlasnaNazwa !== '' ? $wlasnaNazwa : (string) $kategoria->name,
                    'url' => (string) Context::getContext()->link->getCategoryLink($kategoria),
                    'new_tab' => (bool) $link['new_tab'],
                ];

            case 'cms':
                $strona = new CMS((int) $link['id_cms'], $idLang);

                if (!$strona->id || !$strona->active) {
                    return null;
                }

                return [
                    'label' => $wlasnaNazwa !== '' ? $wlasnaNazwa : (string) $strona->meta_title,
                    'url' => (string) Context::getContext()->link->getCMSLink($strona),
                    'new_tab' => (bool) $link['new_tab'],
                ];

            default:
                $adres = trim((string) ($link['urls'][$idLang] ?? ''));

                if ($adres === '' || $wlasnaNazwa === '') {
                    return null;
                }

                return ['label' => $wlasnaNazwa, 'url' => $adres, 'new_tab' => (bool) $link['new_tab']];
        }
    }

    /** @return array<int,string> */
    private function names(int $id): array
    {
        $wiersze = Db::getInstance()->executeS(
            'SELECT id_lang, name FROM `' . _DB_PREFIX_ . 'quince_linkblock_lang`'
            . ' WHERE id_quince_linkblock = ' . $id
        ) ?: [];

        $out = [];

        foreach ($wiersze as $w) {
            $out[(int) $w['id_lang']] = (string) $w['name'];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function links(int $id): array
    {
        $wiersze = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'quince_linkblock_link`'
            . ' WHERE id_quince_linkblock = ' . $id . ' ORDER BY position, id_quince_linkblock_link'
        ) ?: [];

        $out = [];

        foreach ($wiersze as $w) {
            $idLink = (int) $w['id_quince_linkblock_link'];
            $tresci = Db::getInstance()->executeS(
                'SELECT id_lang, label, url FROM `' . _DB_PREFIX_ . 'quince_linkblock_link_lang`'
                . ' WHERE id_quince_linkblock_link = ' . $idLink
            ) ?: [];

            $labels = [];
            $urls = [];

            foreach ($tresci as $t) {
                $labels[(int) $t['id_lang']] = (string) $t['label'];
                $urls[(int) $t['id_lang']] = (string) $t['url'];
            }

            $out[] = [
                'id' => $idLink,
                'type' => in_array($w['type'], self::TYPES, true) ? (string) $w['type'] : 'custom',
                'id_category' => (int) $w['id_category'],
                'id_cms' => (int) $w['id_cms'],
                'new_tab' => (bool) $w['new_tab'],
                'labels' => $labels,
                'urls' => $urls,
            ];
        }

        return $out;
    }

    /**
     * Zapis bloku razem z jego odnośnikami.
     *
     * Odnośniki zapisujemy przez skasowanie i wstawienie od nowa: kolejność
     * i skład zmieniają się razem, więc śledzenie, co doszło, a co zniknęło,
     * kosztowałoby więcej niż samo przepisanie kilkunastu wierszy.
     *
     * @param array<string,mixed> $dane
     */
    public function save(array $dane): int
    {
        $db = Db::getInstance();
        $id = (int) ($dane['id'] ?? 0);

        $blok = [
            'hook' => pSQL((string) ($dane['hook'] ?? 'displayFooter')),
            'active' => (int) (bool) ($dane['active'] ?? true),
            'show_name' => (int) (bool) ($dane['show_name'] ?? true),
            'position' => (int) ($dane['position'] ?? 0),
        ];

        if ($id > 0) {
            $db->update('quince_linkblock', $blok, 'id_quince_linkblock = ' . $id);
        } else {
            $db->insert('quince_linkblock', $blok);
            $id = (int) $db->Insert_ID();
        }

        $db->delete('quince_linkblock_lang', 'id_quince_linkblock = ' . $id);

        foreach (Language::getLanguages(true) as $jezyk) {
            $idLang = (int) $jezyk['id_lang'];

            $db->insert('quince_linkblock_lang', [
                'id_quince_linkblock' => $id,
                'id_lang' => $idLang,
                'name' => pSQL((string) ($dane['names'][$idLang] ?? '')),
            ]);
        }

        $this->saveLinks($id, is_array($dane['links'] ?? null) ? $dane['links'] : []);

        return $id;
    }

    /** @param array<int,array<string,mixed>> $links */
    private function saveLinks(int $id, array $links): void
    {
        $db = Db::getInstance();

        $stare = $db->executeS(
            'SELECT id_quince_linkblock_link FROM `' . _DB_PREFIX_ . 'quince_linkblock_link`'
            . ' WHERE id_quince_linkblock = ' . $id
        ) ?: [];

        foreach ($stare as $s) {
            $db->delete('quince_linkblock_link_lang',
                'id_quince_linkblock_link = ' . (int) $s['id_quince_linkblock_link']);
        }

        $db->delete('quince_linkblock_link', 'id_quince_linkblock = ' . $id);

        $pozycja = 0;

        foreach ($links as $link) {
            $typ = in_array($link['type'] ?? '', self::TYPES, true) ? (string) $link['type'] : 'custom';

            $db->insert('quince_linkblock_link', [
                'id_quince_linkblock' => $id,
                'type' => pSQL($typ),
                'id_category' => (int) ($link['id_category'] ?? 0),
                'id_cms' => (int) ($link['id_cms'] ?? 0),
                'new_tab' => (int) (bool) ($link['new_tab'] ?? false),
                'position' => $pozycja++,
            ]);

            $idLink = (int) $db->Insert_ID();

            foreach (Language::getLanguages(true) as $jezyk) {
                $idLang = (int) $jezyk['id_lang'];

                $db->insert('quince_linkblock_link_lang', [
                    'id_quince_linkblock_link' => $idLink,
                    'id_lang' => $idLang,
                    'label' => pSQL((string) ($link['labels'][$idLang] ?? '')),
                    'url' => pSQL((string) ($link['urls'][$idLang] ?? '')),
                ]);
            }
        }
    }

    public function delete(int $id): void
    {
        $this->saveLinks($id, []);

        Db::getInstance()->delete('quince_linkblock_lang', 'id_quince_linkblock = ' . $id);
        Db::getInstance()->delete('quince_linkblock', 'id_quince_linkblock = ' . $id);
    }

    /**
     * Kategorie sklepu do wyboru w odnośniku.
     *
     * Dane bierze silnik: ten sam zestaw karmi wybieraki na wszystkich naszych
     * ekranach.
     *
     * @return array<int,array{id:int,name:string,depth:int,parent:int,sciezka:int[],dzieci:bool}>
     */
    public function categoryChoices(int $idLang): array
    {
        return CategoryChoices::forPanel($idLang, (int) Context::getContext()->shop->id);
    }

    /**
     * Strony treści do wyboru w odnośniku.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public function cmsChoices(int $idLang): array
    {
        $wiersze = Db::getInstance()->executeS(
            'SELECT c.id_cms, cl.meta_title FROM `' . _DB_PREFIX_ . 'cms` c'
            . ' JOIN `' . _DB_PREFIX_ . 'cms_lang` cl ON cl.id_cms = c.id_cms AND cl.id_lang = ' . $idLang
            . ' WHERE c.active = 1 ORDER BY cl.meta_title'
        ) ?: [];

        return array_map(
            static fn (array $w): array => ['id' => (int) $w['id_cms'], 'name' => (string) $w['meta_title']],
            $wiersze
        );
    }
}
