# Atelier 6 — Gestion des stagiaires

Application PHP/MySQL avec inscription d’un stagiaire, téléversement d’image vers Cloudinary et affichage des profils depuis Aiven. Le navigateur ne communique qu’avec l’API PHP ; les identifiants restent dans les variables d’environnement.

## Structure

- `index.html` : formulaire d’inscription.
- `liste.html` : liste des stagiaires.
- `api/inscrire.php` : validation, téléversement Cloudinary et insertion MySQL.
- `api/liste.php` : lecture des profils au format JSON.
- `config/db.php` : connexion PDO MySQL avec vérification TLS.
- `vercel.json` : runtime PHP pour Vercel.

## Base de données

La table `stagiaires` doit déjà exister dans la base configurée, avec les colonnes `id`, `nom`, `prenom`, `ville`, `photo` et `date_inscription` comme dans l’atelier. L’application ne crée ni ne modifie le schéma.

## Variables Vercel

Dans **Project → Settings → Environment Variables**, ajoutez les valeurs issues de vos services Aiven et Cloudinary pour les environnements visés (au minimum Production) :

| Variable | Valeur |
| --- | --- |
| `DB_HOST` | Hostname Aiven, sans `https://` |
| `DB_PORT` | Port MySQL Aiven |
| `DB_NAME` | Nom de la base, souvent `defaultdb` |
| `DB_USER` | Utilisateur MySQL Aiven |
| `DB_PASSWORD` | Mot de passe MySQL Aiven |
| `CLOUDINARY_URL` | URL complète au format `cloudinary://API_KEY:API_SECRET@CLOUD_NAME` |

Au lieu de `CLOUDINARY_URL`, vous pouvez définir les trois variables `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY` et `CLOUDINARY_API_SECRET`. **Une API Key seule ne suffit pas** pour un téléversement signé. La valeur de `CLOUDINARY_URL` doit être l’URL complète affichée dans Cloudinary; ne mettez pas seulement la clé dans cette variable.

Ne mettez pas les mots de passe ou `CLOUDINARY_URL` dans les fichiers suivis par Git. Vercel doit fournir ces valeurs au runtime PHP.

## Certificat TLS Aiven

Téléchargez **CA Certificate** depuis la page Overview de votre service MySQL Aiven. Vous pouvez soit placer le fichier sous `certs/ca.pem`, soit créer une variable Vercel `DB_CA_CERT` contenant le PEM complet. La connexion refuse de démarrer si le certificat manque et conserve la vérification du certificat TLS.

## Déploiement Vercel

1. Publiez ce dossier dans un dépôt GitHub (ne publiez jamais `.env`, les secrets, `vendor/` ou le certificat privé de service).
2. Importez le dépôt dans Vercel. Si ce dossier est dans un dépôt parent, définissez **Root Directory** sur `atelier6-stagiaires`.
3. Configurez les variables ci-dessus et le certificat CA dans Vercel.
4. Lancez le déploiement. `composer.json` déclare le SDK Cloudinary PHP utilisé par l’API.
5. Ouvrez l’URL du projet, testez une inscription, puis ouvrez `/liste.html`.

## Développement local

Avec PHP, Composer et les extensions `pdo_mysql`, `fileinfo` et `curl` installés :

```sh
composer install
```

Configurez les variables listées dans `.env.example` dans l’environnement de votre terminal, placez le certificat sous `certs/ca.pem` (ou configurez `DB_CA_CERT`), puis démarrez le serveur depuis ce dossier :

```sh
php -S localhost:8000
```

Ne mettez jamais vos secrets dans un fichier suivi par Git.
