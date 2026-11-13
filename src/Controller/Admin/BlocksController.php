<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\LinkBlocks\Controller\Admin;

use Quince\Engine\Controller\Admin\QuinceController;
use Quince\LinkBlocks\Settings\LinkBlocks;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloki odnośników.
 *
 * Dziedziczy po klasie z silnika, więc ekran dostaje ten sam sidebar, pasek
 * zapisu i wygląd co reszta panelu Quince.
 */
class BlocksController extends QuinceController
{
    public function __construct(private readonly LinkBlocks $blocks)
    {
    }

    public function indexAction(): Response
    {
        $idLang = (int) $this->getContext()->language->id;

        return $this->renderPanel('@Modules/quince_linkblocks/views/templates/admin/blocks.html.twig', [
            'blocks' => $this->blocks->all(),
            'categories' => $this->blocks->categoryChoices($idLang),
            'pages' => $this->blocks->cmsChoices($idLang),
            'languages' => $this->languages(),
            'layoutTitle' => 'Link blocks',
            'layoutTitleDomain' => 'Modules.Quincelinkblocks.Admin',
            'quinceRoute' => 'quince_linkblocks',
        ]);
    }

    /**
     * Zapis wszystkich bloków naraz.
     *
     * Ekran jest jednym formularzem, więc zapis też: bloki, ich nazwy i ich
     * odnośniki przychodzą razem i razem zastępują stan poprzedni. Blok, który
     * zniknął z formularza, znika też z bazy.
     */
    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_linkblocks_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quincelinkblocks.Admin'));

            return $this->redirectToRoute('quince_linkblocks');
        }

        $przyslane = $request->request->all('block');
        $zostaja = [];
        $pozycja = 0;

        foreach (is_array($przyslane) ? $przyslane : [] as $blok) {
            if (!is_array($blok)) {
                continue;
            }

            $blok['position'] = $pozycja++;
            $zostaja[] = $this->blocks->save($blok);
        }

        // Bloki, których w formularzu nie było, zostały skasowane w panelu.
        foreach ($this->blocks->all() as $istniejacy) {
            $id = (int) $istniejacy['id'];

            if (!in_array($id, $zostaja, true)) {
                $this->blocks->delete($id);
            }
        }

        $this->registerHooks();

        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quincelinkblocks.Admin'));

        return $this->redirectToRoute('quince_linkblocks');
    }

    public function deleteAction(int $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_linkblocks_delete', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quincelinkblocks.Admin'));

            return $this->redirectToRoute('quince_linkblocks');
        }

        $this->blocks->delete($id);
        $this->registerHooks();

        $this->addFlash('success', $this->trans('Block removed.', 'Modules.Quincelinkblocks.Admin'));

        return $this->redirectToRoute('quince_linkblocks');
    }

    /**
     * Po zapisie moduł musi być zarejestrowany na hookach, których używają bloki.
     * Inaczej nowy hook wpisany w panelu nie wyświetli niczego.
     */
    private function registerHooks(): void
    {
        $modul = \Module::getInstanceByName('quince_linkblocks');

        if ($modul instanceof \Module) {
            $modul->registerHooks();
        }
    }
}
