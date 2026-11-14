# Publicar este proyecto con otra cuenta de GitHub

Estado comprobado el 2026-11-12: el repositorio local ya está inicializado en `main`, sin commits ni remotos. No se creó un repositorio ni se hizo push durante la revisión. Estos pasos seleccionan la identidad de este proyecto mediante configuración local y una clave SSH propia.

## 1. Crear el repositorio vacío

Entra a GitHub con la cuenta de destino. Crea un repositorio con el nombre y la visibilidad que quieras. Déjalo vacío, sin inicializar README, licencia ni `.gitignore`, porque esos archivos ya existen localmente.

## 2. Configurar el autor solo en este repositorio

Sustituye nombre y correo por los de la cuenta de destino; usa un correo verificado por esa cuenta o su dirección noreply.

```sh
cd /Users/davidmartinez/Desktop/proyectos/NotificacionHub
git config --local user.name 'TU NOMBRE'
git config --local user.email 'TU CORREO'
```

Esto configura la autoría de los commits. La cuenta que autentica el push se selecciona por la clave SSH del siguiente paso. No necesitas cambiar `git config --global` ni cerrar la sesión de otra cuenta.

## 3. Preparar una clave para la cuenta de destino

Si ya tienes una clave de esa cuenta, reutiliza su ruta en los siguientes comandos. Para crear otra, comprueba primero que el nombre elegido no esté ocupado; no sobrescribas una clave existente.

```sh
ssh-keygen -t ed25519 -C 'TU CORREO' -f /Users/davidmartinez/.ssh/id_ed25519_notificationhub
pbcopy < /Users/davidmartinez/.ssh/id_ed25519_notificationhub.pub
```

Añade el contenido público copiado en GitHub, dentro de la cuenta de destino: **Settings → SSH and GPG keys → New SSH key**. Conserva la clave privada en tu equipo.

## 4. Seleccionar y comprobar la cuenta usada por este proyecto

```sh
git config --local core.sshCommand 'ssh -F /dev/null -i /Users/davidmartinez/.ssh/id_ed25519_notificationhub -o IdentitiesOnly=yes'
ssh -T -F /dev/null -i /Users/davidmartinez/.ssh/id_ed25519_notificationhub -o IdentitiesOnly=yes git@github.com
```

La primera conexión puede pedir confirmar la huella pública de GitHub: compárala con la documentación oficial enlazada abajo. El saludo debe mostrar `Hi USUARIO_DESTINO!`. GitHub no ofrece shell y esa prueba termina normalmente con código 1 incluso si la autenticación fue correcta. Si muestra otra cuenta, corrige la clave antes del push.

## 5. Revisar y crear el primer commit

```sh
git status --short
git check-ignore .env project_back/.env project_back/vendor/autoload.php artifacts-security.example/report.json
git add .
git diff --cached --check
git diff --cached --stat
git diff --cached --name-only
```

`check-ignore` debe listar las rutas excluidas. En la lista preparada para commit no deben aparecer `.env`, `vendor/`, bases SQLite, claves privadas, logs ni carpetas `artifacts-security.*`. Los archivos `.env.example` sí forman parte del proyecto. Revisa también el informe final: publicar el código no equivale a cerrar los controles pendientes.

Cuando la revisión sea correcta:

```sh
git commit -m 'Initial commit: Notification Hub API and Docker environment'
```

## 6. Conectar el repositorio y subir

Reemplaza `CUENTA_DESTINO` y `REPOSITORIO` por los valores creados en el paso 1.

```sh
git remote add origin git@github.com:CUENTA_DESTINO/REPOSITORIO.git
git remote -v
git push -u origin main
git status --short --branch
```

Si en ese momento `origin` ya existe, revisa su URL y utiliza `git remote set-url origin ...` solo si necesitas corregirlo. No fuerces el push si el repositorio remoto ya contiene commits; primero revisa ese historial.

## Referencias

- [Autor por repositorio](https://docs.github.com/en/get-started/git-basics/setting-your-username-in-git).
- [Crear una clave SSH](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent).
- [Añadir la clave pública a la cuenta](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/adding-a-new-ssh-key-to-your-github-account).
- [Comprobar autenticación SSH](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/testing-your-ssh-connection).
- [Huellas públicas de GitHub](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints).
