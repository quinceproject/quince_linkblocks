<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Quince\LinkBlocks\Settings\LinkBlocks;

/**
 * Quince Link Blocks — bloki odnośników do stopki.
 *
 * Blok to nagłówek i lista odnośników: kategorie sklepu, strony treści albo
 * własne adresy. Blok nie wie, gdzie stanie — deklaruje hook, a stopka silnika
 * przypisuje hooki do kolumn. Dzięki temu przestawienie kolumn nie wymaga
 * ruszania bloków, a dołożenie bloku nie wymaga zmiany w motywie.
 *
 * Odnośnik do kategorii i do strony treści trzyma sam numer: nazwę i adres
 * bierzemy przy wyświetlaniu, więc zmiana nazwy kategorii w katalogu jest
 * widoczna w stopce od razu, bez wchodzenia tutaj.
 */
class Quince_Linkblocks extends Module
{
    public function __construct()
    {
        $this->name = 'quince_linkblocks';
        $this->tab = 'front_office_features';
        $this->version = '0.1.0';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        // Ekrany modułu dziedziczą po kontrolerze silnika, a szablony po jego
        // layoucie. Bez tej deklaracji PrestaShop pozwoli wyłączyć silnik,
        // a moduł przestanie otwierać swoje ekrany bez żadnego ostrzeżenia.
        $this->dependencies = ['quince_engine'];

        // Jeden wpis w gałęzi „Quince project" w menu PrestaShopa, prowadzący
        // na ekran modułu. Podstron nie zgłaszamy: moduł ma je we własnym
        // bocznym pasku, a menu sklepu urosłoby wtedy o kilkanaście pozycji.
        // Kolejność wpisów w gałęzi ustala silnik.
        $this->tabs = [
            [
                'class_name' => 'AdminQuinceLinkblocks',
                'visible' => true,
                'name' => 'Link blocks',
                'parent_class_name' => 'AdminQuinceProject',
                'route_name' => 'quince_linkblocks',
                'wording' => 'Link blocks',
                'wording_domain' => 'Modules.Quincelinkblocks.Admin',
            ],
        ];

        parent::__construct();

        $this->displayName = $this->trans('Quince link blocks', [], 'Modules.Quincelinkblocks.Admin');
        $this->description = $this->trans(
            'Blocks of links for the footer: categories, content pages and addresses of your own.',
            [],
            'Modules.Quincelinkblocks.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Uninstall the module? The blocks and their links will be removed.',
            [],
            'Modules.Quincelinkblocks.Admin'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && (new LinkBlocks())->installTables()
            && $this->registerHook('actionQuinceRegisterFeature')
            && $this->registerHooks();
    }

    public function uninstall(): bool
    {
        return (new LinkBlocks())->dropTables() && parent::uninstall();
    }

    /**
     * Rejestracja na hooki, w które bloki się wyświetlają.
     *
     * Blok wskazuje hook, więc lista nie jest znana z góry — rejestrujemy się na
     * te, których używają istniejące bloki, plus wspólny hook stopki jako punkt
     * wyjścia dla sklepu, który nie ustawił jeszcze niczego.
     */
    public function registerHooks(): bool
    {
        foreach ((new LinkBlocks())->hooksInUse() as $hook) {
            if (!$this->isRegisteredInHook($hook) && !$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Zgłoszenie do silnika: co dostarczamy i gdzie tego szukać.
     *
     * Bez tego moduł działałby, ale panel silnika nie wiedziałby o jego ekranie,
     * a ustawienia stopki nie miałyby jak pokazać, że bloki w ogóle istnieją.
     *
     * @return array<int,array{type:string,id:string,label:string,route:string}>
     */
    public function hookActionQuinceRegisterFeature(): array
    {
        return [[
            'type' => 'footer_links',
            'id' => 'quince_linkblocks',
            'label' => $this->trans('Link blocks', [], 'Modules.Quincelinkblocks.Admin'),
            'route' => 'quince_linkblocks',
        ]];
    }

    /**
     * Wyświetlenie bloków przypisanych do hooka, w którym moduł został wywołany.
     *
     * PrestaShop woła `hookDisplayFooter`, `hookDisplayFooterCompany` i tak dalej —
     * zamiast pisać metodę na każdy możliwy hook, rozpoznajemy nazwę z wywołania.
     *
     * @param string $method
     * @param array<int,mixed> $arguments
     */
    public function __call($method, $arguments): string
    {
        if (!str_starts_with($method, 'hookDisplay')) {
            return '';
        }

        return $this->renderHook(lcfirst(substr($method, 4)));
    }

    /** Bloki przypisane do jednego hooka, gotowe do wstawienia w stopkę. */
    public function renderHook(string $hook): string
    {
        $blocks = (new LinkBlocks())->forHook($hook, (int) $this->context->language->id);

        if ($blocks === []) {
            return '';
        }

        $this->smarty->assign(['quince_linkblocks' => $blocks]);

        return $this->display(__FILE__, 'views/templates/hook/blocks.tpl');
    }

    /**
     * Przycisk „Konfiguruj" w menedżerze modułów.
     *
     * PrestaShop pokazuje go po samym istnieniu tej metody
     * (`ModuleRepository::__construct`, `method_exists($tmpModule, 'getContent')`),
     * a my przenosimy sprzedawcę na ekran modułu w panelu.
     */
    public function getContent(): void
    {
        \Tools::redirectAdmin($this->get('router')->generate('quince_linkblocks'));
    }

}
