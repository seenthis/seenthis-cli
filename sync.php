<?php

# seenthis sync:

# export
define('_DIR_EXPORT', 'export/');
define('_VERBOSE', false);

# import

#chdir ('..');
include_once 'ecrire/inc_version.php';
include_spip('base/abstract_sql');
include_spip('inc/filtres');
include_spip('inc/charsets');
date_default_timezone_set('Europe/Paris');

### utiliser le format base36 pour les liens

/*
 * Exemples d'utilisation
 */

// tout exporter en mode text
seenthis_sync_export(100000000, array('html' => false, 'overwrite' => false));

// pour les partages, un lien unix

#seenthis_sync_export();

// importer un message au format email
#seenthis_sync_import_one('../../Source/seenthis-messages/fil/2014/201410/20141004-je-me-lance-dans-l-upgrade-du-serveur-de-seenthis-net-de-squeeze-a-wheezy-prevoir-plantage,6epp.eml');
#exit;

// importer tous les messages du dossier janvier 2011
#foreach (glob('tmp-import/*/*/*/*.eml') as $m) seenthis_sync_import_one($m);
#exit;

/*
 *  Import all recent files
 */
function seenthis_sync_import($age) {
	# find _DIR_EXPORT -mtime -$age
}

/*
 * Export recent messages to disk;
 * int $limit : number of most recent articles to export
 * array $options (
 *    bool $html : export an HTML version as well
 *    bool $overwrite : erase files even if they are most recent than database
 * )
 *
 */
function seenthis_sync_export($limit = 10, $options = array()) {
	include_spip('base/abstract_sql');
	$s = sql_query('SELECT m.*, t.texte, a.login, a.nom AS name, p.uuid puuid
		FROM spip_me m
		JOIN spip_me_texte t ON m.id_me = t.id_me
		JOIN spip_auteurs a ON a.id_auteur=m.id_auteur
		LEFT JOIN spip_me p ON p.id_me=m.id_parent
		WHERE 1=1
		/* AND m.statut="supp" */
		/* AND m.id_auteur IN (5,7,8,10,16,3393) */
		AND m.date_modif >  NOW() - INTERVAL 30 DAY /* */
		/* ORDER BY m.date ASC */ LIMIT '.intval($limit));
	while ($t = sql_fetch($s)) {
		// echo $t[id_me];
		seenthis_sync_export_one($t, $options);
	}
}

/*
 * import one file in eml format into seenthis
 */
function seenthis_sync_import_one($f) {
	if (!$m = seenthis_sync_parse(file_get_contents($f))) {
		echo "Could not parse $f\n";
		return false;
	} else {
		$m['date_modif'] = @filemtime($f);
	}

	// response ?
	if (isset($m['puuid'])
	AND !$p = sql_fetsel('id_me', 'spip_me', 'uuid='.sql_quote($m['puuid']))) {
		echo "Parent not yet known $m[puuid]\n";
		return false;
	}
	$id_parent = $p ? array_pop($p) : 0;

	// est-ce qu'on prend les messages de userkey ?
	// à quel user (à créer éventuellement) correspond-t-il ?
	if ($id_auteur = seenthis_sync_auteur($m)) {
		if ($id_me = sql_fetsel('id_me', 'spip_me', 'uuid='.sql_quote($m['uuid']))) {
			echo "Message already known… need to check date_modif\n";
			return false;
		} else {
			## temporaire pour restaurer seenthis crashé
			if ($m['id_me']) $id_me = $m['id_me']; else $id_me=0;
			##
			instance_me ($id_auteur, $m['text'], $id_me, $id_parent, $m['date'], $m['uuid']);
		}
		return $m['uuid'];
	}
	else {
		echo "user $m[name] unknown\n";
		return false;
	}
}


function seenthis_sync_auteur($m) {
	static $mem = array();

	if (isset($mem[$m['userkey']]))
		return $mem[$m['userkey']];

#	if ($m['userkey'] == 'fil@seenthis.net')
#		return $mem[$m['userkey']] = 1;

	// autres auteurs : les chercher ou les creer
	if(preg_match('/^(\w+)@seenthis.net$/', $m['userkey'], $r)) {
		if ($p = sql_fetsel('id_auteur', 'spip_auteurs', 'login='.sql_quote($r[1])))
			return $mem[$m['userkey']] = $p['id_auteur'];

		if ($id_auteur = sql_insertq('spip_auteurs', array(
			'login' => $r[1],
			'nom' => sinon($m['name'], $r[1]),
			'statut' => '6forum',
		))) {
			echo "creation auteur $id_auteur = $r[1]\n";
			return $mem[$m['userkey']] = $id_auteur;
		}
	}

	return $mem[$m['userkey']] = null; // par défaut, refuser

	return $mem[$m['userkey']] = 1; // auteur 1 par défaut
}

function seenthis_sync_parse($c) {
	$headers = array();
	list($h, $b) = explode("\n\n", $c, 2);

	while (preg_match('/^([\w-]+): (.*(\n .*)*)\n/S', $h, $r)) {
		$headers[strtolower($r[1])] = $r[2];
		$h = substr($h, strlen($r[0]));
	}

	if (preg_match(',\n\n-- <http://.*messages/([0-9]+)>\n$,', $b, $r)) {
		$m['id_me'] = $r[1];
	}


	$body = trim(preg_replace(',\n\n-- <http://.*messages/.*>\n$,', '', $b));

	// convert headers into seenthis fields
	$m = array();

	if (isset($headers['message-id'])
	AND preg_match('/^<?([0-9a-f-]+)(@.*)?>?$/Si', trim($headers['message-id']), $r)
	)
		$m['uuid'] = $r[1];

	if (isset($headers['in-reply-to'])
	AND preg_match('/^<?([0-9a-f-]+)(@.*)?>?$/Si', trim($headers['in-reply-to']), $r))
		$m['puuid'] = $r[1];

	if (isset($headers['date'])
	AND $date = DateTime::createFromFormat(
		'D, d M Y H:i:s O', $headers['date']
	))
		$m['date'] = $date->format( 'Y-m-d H:i:s');

	if (isset($headers['from'])
	AND preg_match('/^(.*?)<([^<>]+)>$/Si', trim($headers['from']), $r)) {
		$m['name'] = trim($r[1]);
		$m['userkey'] = $r[2];
	}
	# ignore subject line


	$m['text'] = $body;

	if (isset($headers['status'])
	AND preg_match('/canceled/Si', trim($headers['message-id']), $r)
	) {
		$m['statut'] = 'supp';
		$m['text'] = null;
	} else {
		$m['statut'] = 'publi';
	}

	// sanity checks


	return $m;
}

function seenthis_sync_export_one($t, $options = array()) {
	$d = strtotime($t['date']);
	if ($d < 0) $d = 0;  // cas d'un bug ou date=0000-00…

	$titre = extraire_titre($t['texte']);
	$tt = strtolower(translitteration($titre));
	$tt = trim(preg_replace('/[^a-z0-9_]+/', '-', $tt),'-');

	// $l = "l/login/"
	// $l = substr($t['login'],0,1).'/' .$t['login'].'/';
	// $l = "login/"
	$l = $t['login'];

	// cas du login vide (compte supprime)
	// if ($l == '//') $l = '_/_/';
	if ($l == '') $l = '_';


	$fmt = _DIR_EXPORT.'%s/%s/%s/%s-%s,%s.eml';
	
	$fuid = sprintf($fmt,
		$l,
		date('Y', $d),
		date('Ym', $d),
		date('Ymd', $d),
		'*',
		base_convert($t['id_me'],10,36)
		);
	$f = str_replace('*', (strlen($tt) ? $tt : 'untitled'), $fuid);

	#echo $f,"\n";

	is_dir(dirname($f)) || mkdir (dirname($f), 0755, true);

	if(_VERBOSE) echo "exporting $t[id_me] to $f\n";


	# $overwrite = false
	if (!$options['overwrite']
	AND ($last = strtotime($t['date_modif']))
	AND (@filemtime($f) >= $last)) {
		if(_VERBOSE) echo "do not rewrite. ";
		if (@filemtime($f) == $last)
			if(_VERBOSE) echo "on-disk is the same\n";
		else
			if(_VERBOSE) echo "on-disk is more recent.\n";
		return;
	}

	// supprimer un message qui porterait le même identifiant
	// on ne le unlink pas, on le renomme, pour eviter un instant de "trou"
	if ($a = glob($fuid)) {
		foreach ($a as $c)
			if ($c != $f)
				rename($c,$f);
	}

	if ($t['statut'] !== 'publi') {
		$t['texte'] = '--canceled--';
		$status = 'canceled';
		$t['titre'] = 'canceled';
		$url = '';
		if (file_exists($f)) unlink($f);
		return;
	} else {
		$status = 'published';
		$url = 'http://seenthis.net/messages/'.
			($t['id_parent']
				? $t['id_parent'].'#message'.$t['id_me']
				: $t['id_me']
			);
	}

	$userkey = $t['login'].'@seenthis.net';

	$headers = sprintf(
"Message-ID: <%s>
Date: %s
From: %s <%s>
Reply-To: Please reply on the Web <no-reply@localhost>
Subject: %s
Status: %s
List-Id: %s
",
	$t['uuid'].'@seenthis.net',
	date('r', strtotime($t['date'])),
	encodeHeader($t['name']), $userkey,
	encodeHeader(html_entity_decode($titre)),
	$status,
	'<'.$t['login'].'.'.'seenthis.net'.'>'
	);

	if ($url) {
		$headers .= sprintf("List-Archive: %s\n", $url);
	}

	if ($t['puuid'])
		$headers .= sprintf("In-Reply-To: <%s>\n", $t['puuid'].'@seenthis.net')
			. sprintf("References: <%s>\n", $t['puuid'].'@seenthis.net');

	if ($t['date_modif'] != $t['date'])
		$headers .= sprintf("Updated: %s\n", date('r', strtotime($t['date_modif'])));


	# export HTML ?  Mais il faudrait un HTML plus modeste. Et que faire des images ? Les ajotuer dans le multipart ?
	if ($options['html']
	AND $payload = microcache($t['id_me'], "noisettes/afficher_message")) {
		while ($c = rand(0,1000000) AND preg_match("/part$c/", $t['texte'])){};
		$headers .= "MIME-Version: 1.0\nContent-Type: multipart/alternative; boundary=\"part$c\"";
		$body = "
--part$c
Content-Type: text/plain; charset=utf-8

".$t['texte']."

--part$c
Content-Type: text/html; charset=utf-8

".$payload."\n";
	}
	else {
		$headers .= "MIME-Version: 1.0\nContent-Type: text/plain; charset=utf-8\n";
		$body = $t['texte'];
		if ($url)
			$body .= "\n\n\n-- <$url>";
	}

	$message = "$headers\n\n$body\n";

	if (ecrire_fichier($f,$message)) {
		touch ($f, strtotime($t['date_modif']));
	}

}



function encodeHeader($input, $charset = 'utf-8')
{
	if (preg_match('/(\w*[\x80-\xFF]+\w*)/', $input))
		$input=mb_encode_mimeheader($input,$charset, 'Q');
	return $input;
}


function git_add($dir, $f, $user, $date, $s=null) {
	$root = getcwd();
	chdir($dir);
	if (!is_dir('.git')) {
		`git init`;
	}

	$_f = escapeshellarg($f);
	$_d = escapeshellarg($date);
	$_u = escapeshellarg($user);
	$_s = escapeshellarg(isset($s) ? $s : "ajout dans $dir");
	echo `git add $_f && GIT_AUTHOR_NAME=$_u GIT_COMMITTER_NAME="seenthis" GIT_AUTHOR_DATE=$_d GIT_COMMITTER_DATE=$_d GIT_AUTHOR_EMAIL=$_u@seenthis.net GIT_COMMITTER_EMAIL="root@seenthis.net" git commit -m$_s`;

	chdir($root);
}