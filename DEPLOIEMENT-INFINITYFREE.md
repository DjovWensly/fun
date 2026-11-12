# Mettre Soirée Jeux en ligne sur InfinityFree (fun.gamer.gd)

Ce guide met le site directement à la racine de **https://fun.gamer.gd/**. Compte environ
30 à 45 minutes la première fois, en plus de l'attente éventuelle pour que l'adresse soit active.

Ce qu'il faut avoir sous la main :
- ton compte InfinityFree (espace client sur `dash.infinityfree.com`) ;
- les fichiers du site (étape 1) ;
- un mot de passe pour l'administration du site, que tu choisis maintenant.

---

## Étape 1 : récupérer la dernière version des fichiers

Prends les fichiers de la branche GitHub qui contient toutes les modifications :

1. Ouvre `https://github.com/DjovWensly/fun`.
2. En haut à gauche, choisis la branche **`claude/pensive-pascal-tijflm`**.
3. Bouton vert **Code**, puis **Download ZIP**.
4. Décompresse le ZIP sur ton ordinateur.

N'utilise **pas** le `config.php` de ta copie XAMPP : il contient `localhost` et `root`, qui ne
marchent pas sur InfinityFree. On en crée un nouveau à l'étape 5.

---

## Étape 2 : créer l'adresse fun.gamer.gd

Deux possibilités, choisis-en une.

**A. Un nouveau compte d'hébergement (le plus simple)**
1. Dans l'espace client : **Create Account**.
2. Choisis un sous-domaine gratuit : tape `fun` et sélectionne `gamer.gd`.
3. Termine la création. Le dossier du site sera **`htdocs`**.

**B. Ajouter fun.gamer.gd à ton compte existant (celui de dwens.gamer.gd)**
1. Ouvre le **Control Panel** du compte, section **Subdomains**.
2. Ajoute `fun` sur `gamer.gd`.
3. Le dossier du site sera **`fun.gamer.gd/htdocs`** (et non le `htdocs` de dwens).

Une nouvelle adresse peut mettre de quelques minutes à quelques heures pour fonctionner partout.
Tu peux continuer les étapes pendant ce temps.

---

## Étape 3 : créer la base de données

1. Control Panel du compte qui héberge fun.gamer.gd → **MySQL Databases**.
2. Crée une base, par exemple `jeux`. Son nom complet ressemblera à `if0_12345678_jeux`.
3. Note ces 4 informations, elles servent à l'étape 5 :

| Information | Où la trouver | Exemple |
|---|---|---|
| Hôte (`db_host`) | page MySQL Databases, « MySQL Hostname » | `sql123.infinityfree.com` |
| Nom de la base (`db_name`) | la base que tu viens de créer | `if0_12345678_jeux` |
| Utilisateur (`db_user`) | « MySQL Username » | `if0_12345678` |
| Mot de passe (`db_pass`) | espace client → ton compte → **Account Password** (bouton pour l'afficher) | ton mot de passe d'hébergement |

L'hôte n'est **jamais** `localhost` sur InfinityFree.

---

## Étape 4 : créer les tables

1. Sur la page MySQL Databases, clique **phpMyAdmin** à côté de ta base.
2. Dans phpMyAdmin, clique sur le nom de ta base à gauche.
3. Onglet **Importer** → choisis le fichier **`install.sql`** (dans le dossier décompressé) → **Exécuter**.

Tu dois voir 7 tables : `gp_rooms`, `gp_players`, `gp_prompts`, `gp_emotes`, `gp_settings`, `gp_strokes`, `gp_history`.
Si l'import échoue, ce n'est pas bloquant : `check.php` (étape 7) a un bouton qui crée les
tables principales, et les autres se créent toutes seules au premier usage.

---

## Étape 5 : préparer config.php

Dans le dossier décompressé, copie `config.sample.php` et renomme la copie en **`config.php`**.
Ouvre-la avec le Bloc-notes (ou Notepad++) et remplace **tout** le contenu par :

```php
<?php
return [
    'db_host' => 'sql123.infinityfree.com',   // ton hôte MySQL (étape 3)
    'db_name' => 'if0_12345678_jeux',         // ta base
    'db_user' => 'if0_12345678',              // ton utilisateur
    'db_pass' => 'ton-mot-de-passe',          // mot de passe d'hébergement

    'admin_password' => 'un-mot-de-passe-long-et-different',

    'home_url' => 'https://dwens.gamer.gd/',  // lien "retour" en haut du site
    'room_ttl_hours' => 24,
];
```

Points à vérifier :
- `<?php` est la toute première chose du fichier (aucun espace ni ligne vide avant) ;
- `return [` au début et `];` à la fin ;
- chaque ligne de réglage se termine par une virgule ;
- `admin_password` est différent du mot de passe d'hébergement.

---

## Étape 6 : envoyer les fichiers

### Ce qu'il faut envoyer dans le dossier du site (`htdocs` ou `fun.gamer.gd/htdocs`)

```
.htaccess          ← fichier caché, ne pas l'oublier
admin.php
api.php
check.php
config.php         ← celui de l'étape 5
index.php
local.php
room.php
assets/            ← tout le dossier (css, js, js/games)
data/              ← prompts.json, reactions.json et manifest.json
img/               ← favicon.ico et apple-touch-icon.png (tes icônes)
inc/               ← tout le dossier, y compris inc/.htaccess et inc/games/
```

### Ce qu'il ne faut pas envoyer
`install.sql`, `README.md`, `DEPLOIEMENT-INFINITYFREE.md`, `config.sample.php`, `.gitignore`.
Ils ne servent à rien en ligne.

### Comment envoyer

**Avec le File Manager d'InfinityFree** (dans le navigateur) :
1. Control Panel → **Online File Manager**.
2. Ouvre le bon dossier (`htdocs` ou `fun.gamer.gd/htdocs`).
3. S'il contient un fichier d'accueil par défaut (par exemple `index2.html`), supprime-le.
4. Envoie les fichiers et les dossiers. Le plus rapide : faire un ZIP des fichiers ci-dessus, l'envoyer,
   puis utiliser **Extract** dans le File Manager.

**Avec FileZilla** (plus fiable pour beaucoup de fichiers) :
- Hôte : `ftpupload.net`, port `21`.
- Utilisateur : ton `if0_12345678`, mot de passe : ton mot de passe d'hébergement.
- Pour voir et envoyer les `.htaccess` : menu **Serveur → Forcer l'affichage des fichiers cachés**.

Vérifie à la fin que `index.php` est **directement** dans le dossier du site, et pas dans un
sous-dossier du ZIP (par exemple `fun-claude-pensive-pascal-tijflm/index.php`).

---

## Étape 7 : vérifier avec check.php

Ouvre **https://fun.gamer.gd/check.php**. Chaque ligne doit être verte :

| Ligne en rouge | Solution |
|---|---|
| config.php not found / does not return the settings | refaire l'étape 5 et renvoyer `config.php` |
| MySQL rejected the user or password | vérifier `db_user` et `db_pass` (mot de passe d'hébergement, pas celui de l'espace client si différent) |
| Cannot reach the MySQL server | `db_host` doit être `sqlXXX.infinityfree.com`, pas `localhost` |
| Database not found | vérifier `db_name` (nom complet avec `if0_..._`) |
| Table gp_rooms / gp_players is missing | cliquer **Create the tables** sur la page |
| Game API did not answer correctly | relire le message affiché dessous, il donne la cause |

Quand tout est vert (« Everything is ready »), tu peux supprimer `check.php` du serveur.
Garde-le sur ton ordinateur pour le renvoyer en cas de problème.

---

## Étape 8 : HTTPS (cadenas)

Ouvre `https://fun.gamer.gd/`. Si le cadenas n'apparaît pas ou si le navigateur affiche une erreur :
1. Espace client → ton compte → **Free SSL Certificates** (ou SSL/TLS).
2. Demande un certificat pour `fun.gamer.gd` et suis les étapes (validation, puis installation).

Le HTTPS est utile pour le bouton **Copier le lien** (sans HTTPS il affiche le lien dans une fenêtre
à copier à la main) et pour la confiance des joueurs.

---

## Étape 9 : régler le site depuis l'admin

Ouvre **https://fun.gamer.gd/admin.php** et connecte-toi avec `admin_password`.

- **Settings** :
  - textes du site (nom, titre, phrase d'accueil) en FR et EN, si tu veux autre chose que « Soirée Jeux » ;
  - jeux affichés sur l'accueil ;
  - lien vers ton site principal : `https://dwens.gamer.gd/` et son texte ;
  - **vitesse de rafraîchissement** : garde 2 s au début ; passe à 3 s si tu approches du quota ;
  - durée de conservation des tables inactives.
- **Reactions** : tes emoji et phrases (« Ayibobo ! »…), jusqu'à 16.
- **Questions** : les cartes d'Action ou Vérité, Qui de nous deux (type *who*), Tu préfères
  (type *wyr*, deux choix séparés par `||`) et les mots de Dessine et devine (type *word*).
  Tes questions faites sur XAMPP ne sont pas copiées automatiquement : sur XAMPP, onglet Questions,
  **Export JSON** te donne une sauvegarde à recopier si besoin.

---

## Étape 10 : tester comme un vrai joueur

Avec deux appareils (ton téléphone et l'ordinateur, ou deux téléphones) :

1. Sur le premier : nom → **Créer une table** (Dominos par exemple) → note le code.
2. Sur le second : ouvre `https://fun.gamer.gd/`, tape le code → **Entrer**.
3. Lance la partie, joue quelques coups, envoie une **réaction** (bouton rond en bas à gauche).
4. Essaie aussi **Jouer seul** (Dominos ou Morpion 25 × 25) et **Dessine et devine** à deux.

La première ouverture du site peut afficher une courte page de chargement d'InfinityFree
(protection anti-robots) : c'est normal.

---

## Étape 11 : relier depuis dwens.gamer.gd

Sur ton site principal, ajoute un lien, par exemple :

```html
<a href="https://fun.gamer.gd/">Jeux entre amis</a>
```

Lien direct pour rejoindre une table avec un code : `https://fun.gamer.gd/index.php?join=CODE`.

---

## Mises à jour plus tard

1. Renvoie uniquement les fichiers modifiés (même emplacement, remplacer).
2. Ne remplace **jamais** le `config.php` en ligne par celui de XAMPP.
3. Si un changement touche le CSS ou le JavaScript, le numéro `GP_ASSET_VERSION` dans
   `inc/layout.php` doit avoir changé (il l'est dans les versions que je te prépare) ;
   sinon les téléphones peuvent garder l'ancienne version. En cas de doute : Ctrl+F5.
4. Les nouvelles tables éventuelles se créent toutes seules ; `check.php` te dira s'il manque quelque chose.
   (Si tu as déjà mis le site en ligne avant l'historique de la soirée, tu peux aussi réimporter
   `install.sql` dans phpMyAdmin : c'est sans risque, il ne crée que ce qui manque.)

---

## Quota de la version gratuite

InfinityFree autorise environ **50 000 requêtes par jour** pour tout le site.
Repères avec le rafraîchissement à 2 s :

| Situation | Requêtes environ |
|---|---|
| 1 joueur pendant 1 heure (Dominos, Morpion, Action ou Vérité…) | 1 800 |
| 4 joueurs pendant 1 heure | 7 200 |
| 1 joueur pendant 1 heure de Dessine et devine | 3 600 |

Si le site devient indisponible en fin de journée (limite atteinte), passe la vitesse à 3 s ou 5 s
dans **admin.php → Settings**. Le compteur repart à zéro le lendemain.

---

## En cas de problème

| Symptôme | Cause probable |
|---|---|
| « Server error » | ouvrir `check.php` : il vérifie que tous les fichiers sont présents et lance le code de la page d'accueil pour afficher la vraie erreur. Souvent un envoi incomplet : un fichier manquant ou resté dans son ancienne version. Autre option : ajouter `'debug' => true,` dans `config.php` pour voir l'erreur sur la page (le remettre à `false` ensuite) |
| Page blanche ou « Internal Server Error » | renommer temporairement `.htaccess` en `htaccess.txt` pour voir si c'est lui ; vérifier `config.php` |
| « Problème de connexion » dans les jeux | ouvrir `check.php`, il dit quoi corriger |
| Encadré rouge en haut d'une page | il contient l'explication et un lien vers `check.php` |
| La page d'accueil d'InfinityFree s'affiche au lieu du site | fichiers envoyés dans le mauvais dossier, ou `index2.html` encore présent |
| Les anciennes couleurs / anciens textes restent | Ctrl+F5 sur l'ordinateur ; vider le cache du navigateur sur le téléphone |
| Le QR code ne s'affiche pas | il est chargé depuis internet (cdnjs) ; réessayer, le code et le lien marchent quand même |
