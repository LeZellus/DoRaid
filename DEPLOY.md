# Guide de déploiement – DoRaid

## Prérequis (à faire une seule fois)

### 1. Clé SSH configurée
La clé SSH locale doit être copiée sur le serveur pour se connecter sans mot de passe.

```powershell
Get-Content $env:USERPROFILE\.ssh\id_ed25519.pub | ssh -p <PORT> <USER>@<HOST> "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
```

### 2. Execution policy PowerShell débloquée
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

---

## Déployer

Depuis le dossier du projet dans PowerShell :

```powershell
.\deploy.ps1
```

C'est tout.

---

## Ce que fait le script

1. **Build Tailwind** — compile le CSS en local (`php bin/console tailwind:build --minify`)
2. **Commit CSS** — si le CSS a changé, le commite automatiquement
3. **Push** — envoie le code sur GitHub (`git push origin main`)
4. **Déploiement SSH** — se connecte au serveur et exécute :
   - `git pull origin main` — récupère le nouveau code
   - `composer2 install --no-dev` — installe les dépendances prod (retire les packages de dev)
   - `php bin/console doctrine:migrations:migrate` — applique les migrations BDD
   - `php bin/console cache:clear --env=prod` — vide le cache Symfony
   - `php bin/console asset-map:compile` — compile les assets JS/CSS

---

## Notes

- Les packages `require-dev` (PHPUnit, web-profiler…) sont automatiquement retirés en prod par `--no-dev` — c'est normal.
- Tailwind ne peut pas builder sur le serveur (`/tmp noexec`), c'est pourquoi le CSS est compilé localement et versionné.
- En développement, compiler le CSS manuellement : `php bin/console tailwind:build --minify`
