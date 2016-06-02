<?php


if (isset($_SERVER['HTTP_HOST'])) die("commande cli");

#chdir('..');


require_once('ecrire/inc_version.php');

$login = $argv[1];

$password = $argv[2];

if (md5($password) !== 'b6eeb5a4973789c64be43f4485297532') die("Tu m'as pas donne le bon password\n");

// exporter tout
echo "export…";
include "cli/sync.php";
echo " OK\n";

// puis supprimer l'auteur
archiver($login);


function archiver($login) {
	include_spip('base/abstract_sql');
	$auteur = sql_fetsel('id_auteur', 'spip_auteurs', 'login='._q($login));

	if (!$auteur) {
		echo sprintf("auteur %s inexistant", htmlspecialchars($login))."\n";
		return false;
	}

	echo sprintf("auteur %s = %s", htmlspecialchars($login), $auteur['id_auteur'])."\n";
	$a = sql_fetsel('COUNT(*)', 'spip_me', 'id_auteur='.$auteur['id_auteur']);
	$a = array_pop($a);

	echo sprintf("suppression de %s messages", $a)."\n";

	sql_delete('spip_me', 'id_auteur='.$auteur['id_auteur']);



	echo "Nettoyage des textes\n";
	if ($a = sql_allfetsel('a.id_me', 'spip_me_texte a LEFT JOIN spip_me b ON a.id_me=b.id_me', 'b.id_me IS NULL')) {
		foreach ($a as $m) {
			echo "suppression message ", $m['id_me'], "\n";
			sql_delete('spip_me_texte', 'id_me='.$m['id_me']);
		}
	}
	
	echo "Nettoyage des index\n";
	if ($a = sql_allfetsel('a.id_me', 'spip_me_recherche a LEFT JOIN spip_me b ON a.id_me=b.id_me', 'b.id_me IS NULL')) {
		foreach ($a as $m) {
			echo "suppression indexation ", $m['id_me'], "\n";
			sql_delete('spip_me_recherche', 'id_me='.$m['id_me']);
		}
	}
	
	echo "Nettoyage des tags\n";
	if ($a = sql_allfetsel('a.id_me', 'spip_me_tags a LEFT JOIN spip_me b ON a.id_me=b.id_me', 'b.id_me IS NULL', 'a.id_me')) {
		foreach ($a as $m) {
			echo "suppression tags ", $m['id_me'], "\n";
			sql_delete('spip_me_tags', 'id_me='.$m['id_me']);
		}
	}
	
	echo "Nettoyage des sites\n";
	if ($a = sql_allfetsel('a.id_me', 'spip_me_tags a LEFT JOIN spip_me b ON a.id_me=b.id_me', 'b.id_me IS NULL', 'a.id_me')) {
		foreach ($a as $m) {
			echo "suppression tags ", $m['id_me'], "\n";
			sql_delete('spip_me_tags', 'id_me='.$m['id_me']);
		}
	}
	
	echo sprintf("suppression du logo")."\n";
	$glob = _DIR_IMG.'aut{on,off}'.($auteur['id_auteur']).'.{gif,png,jpg}';
	if ($a = glob($glob, GLOB_BRACE)) {
		foreach ($a as $logo) {
			echo "$logo\n";
			unlink($logo);
		}
	}

	echo sprintf("suppression du bandeau et du fond")."\n";
	$glob = _DIR_IMG.'{bandeau,fond}/*/{bandeau,fond}'.($auteur['id_auteur']).'.{gif,png,jpg}';
	if ($a = glob($glob, GLOB_BRACE)) {
		foreach ($a as $logo) {
			echo "$logo\n";
			unlink($logo);
		}
	}

	// on repete qqs fois car il y a la profondeur d'url
	// ne pas trop se casser la tete non plus, ca finira par finir…
	echo "nettoyage des liens\n";
	for ($i = 1; $i<3; $i++) 
	if ($a = sql_allfetsel('a.id_syndic, a.url_site', 'spip_syndic a LEFT JOIN spip_syndic c ON c.id_parent = a.id_syndic LEFT JOIN spip_me_tags b ON a.url_site = b.tag', 'b.tag IS NULL AND c.id_syndic IS NULL')) {
		foreach ($a as $m) {
			echo "suppression url ", $m['url_site'], "\n";
			sql_delete('spip_syndic', 'id_syndic='.$m['id_syndic']);
		}
	}

	echo sprintf("suppression de l'auteur")."\n";
	sql_delete('spip_auteurs', 'id_auteur='.$auteur['id_auteur']);

	echo "desindexation sphinx"."\n";
	echo `echo "delete from seenthis where properties.login='$login';" | mysql -h 127.0.0.1 -P 9306`;

}

