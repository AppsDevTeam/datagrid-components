<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/**
 * Automaticke nacitani dalsich radku obstarava JS ve fancyadminu - hleda grid podle tridy
 * a odkaz "Nacist dalsi" podle data atributu. Sablona je musi vykreslit.
 */

$template = file_get_contents(__DIR__ . '/../src/Component/DataGrid.latte');


test('grid s nekonecnym scrollem ma na obalu vlastni tridu', function () use ($template) {
	Assert::contains('<div class="datagrid datagrid-{$control->getName()} {implode(" ", $gridClasses)}{if $infiniteScroll} datagrid-infinite-scroll{/if}"', $template);
});


test('odkaz pro nacteni dalsich radku nese data atribut pro automaticke nacitani', function () use ($template) {
	Assert::match('~<a n:if="\$control->showLoadMoreButton\(\)" n:href="loadMore! page => \$infinityPage"[^>]*\sdata-datagrid-infinite-scroll[^>]*>~', $template);
});


test('odkaz pro nacteni dalsich radku zustava ajaxovy, aby fungoval i bez automatiky', function () use ($template) {
	Assert::match('~n:href="loadMore! page => \$infinityPage" n:class="ajax, btn, btn-primary, infinite-scroll"~', $template);
});


test('nacteni dalsich radku nezaklada zaznam v historii prohlizece', function () use ($template) {
	// Kazde automaticke donacteni by jinak pridalo krok zpet.
	Assert::match('~data-datagrid-infinite-scroll data-ajax-off=\'\["history"\]\'>~', $template);
});
