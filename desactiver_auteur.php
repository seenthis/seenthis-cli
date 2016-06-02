<?php


if (isset($_SERVER['HTTP_HOST'])) die("commande cli");

#chdir('..');


require_once('ecrire/inc_version.php');

echo "export…";
include "cli/sync.php";
echo " OK\n";

$login = $argv[1];

$password = $argv[2];

if (md5($password) !== 'b6eeb5a4973789c64be43f4485297532') die("Tu m'as pas donne le bon password\n");


// desactiver
// instituer_posts($login, 'publi', 'inact');

// reactiver
instituer_posts($login, 'inact', 'publi');

function instituer_posts($login, $old, $new) {
	include_spip('base/abstract_sql');
	$auteur = sql_fetsel('id_auteur', 'spip_auteurs', 'login='._q($login));

	if (!$auteur) {
		echo sprintf("auteur %s inexistant", htmlspecialchars($login))."\n";
		return false;
	}

	echo sprintf("auteur %s = %s", htmlspecialchars($login), $auteur['id_auteur'])."\n";
	$a = sql_fetsel('COUNT(*)', 'spip_me', 'id_auteur='.$auteur['id_auteur']. " AND statut='$old'");
	$a = array_pop($a);

	echo sprintf("desactivation de %s messages", $a)."\n";

	sql_query("update spip_me set statut='$new' where id_auteur=".$auteur['id_auteur']." and statut='$old' and statut != 'supp'");

}

