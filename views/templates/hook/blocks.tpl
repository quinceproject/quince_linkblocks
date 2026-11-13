{**
 * Quince: bloki odnośników w stopce.
 *
 * Blok nie rysuje własnej kolumny: stoi w tej, do której stopka przypisała jego
 * hook. Stąd sam nagłówek i lista, bez opakowania na szerokość.
 *}
{foreach from=$quince_linkblocks item=block}
  <div class="q-linkblock">
    {if $block.show_name && $block.name}
      <h3 class="q-linkblock__title">{$block.name}</h3>
    {/if}

    <ul class="q-linkblock__list">
      {foreach from=$block.links item=link}
        <li>
          <a class="q-linkblock__link" href="{$link.url|escape:'html':'UTF-8'}"
             {if $link.new_tab}target="_blank" rel="noopener"{/if}>{$link.label}</a>
        </li>
      {/foreach}
    </ul>
  </div>
{/foreach}
