# GymTracker Pro

Aplicacion web para el seguimiento de entrenamientos y evolucion fisica. Permite gestionar rutinas, registrar sesiones de ejercicio y realizar un seguimiento del peso corporal.

## Caracteristicas Principales

* Autenticacion local y mediante Google Identity Services.
* Gestion de rutinas personalizadas por dias.
* Registro detallado de series, repeticiones y peso por ejercicio.
* Historial de entrenamientos con visualizacion de progreso.
* Seguimiento de peso corporal y calculo de IMC.


## Requisitos del Sistema

* Servidor web (Apache recomendado).
* PHP 7.4 o superior.
* MySQL 5.7 o MariaDB 10.3 o superior.
* Soporte para mod_rewrite habilitado en Apache.

## Instalacion

1. Clonar o subir los archivos al servidor web.
2. Importar el archivo `gymtracker_dump.sql` en su base de datos MySQL.
3. Crear un archivo `.env` en la raiz del proyecto basado en el archivo `.env.example`.
4. Configurar las credenciales de la base de datos en el archivo `.env`.

### Configuracion de Google Auth

Para habilitar el inicio de sesion con Google:
1. Crear un proyecto en Google Cloud Console.
2. Configurar la pantalla de consentimiento OAuth.
3. Crear un ID de cliente OAuth 2.0.
4. Añadir el dominio de la aplicacion en origenes de JavaScript autorizados.
5. Copiar el Client ID en la variable `GOOGLE_CLIENT_ID` del archivo `.env`.

## Seguridad

* El acceso externo al archivo `.env` y a scripts de configuracion esta bloqueado mediante `.htaccess`.
* Se recomienda utilizar un usuario de base de datos con permisos limitados (SELECT, INSERT, UPDATE, DELETE) para la aplicacion en produccion.
* Las contraseñas se almacenan de forma segura utilizando password_hash de PHP.

## Notas de Desarrollo

La aplicacion utiliza un cargador de entorno personalizado ubicado en `includes/env_loader.php` para gestionar la configuracion sin necesidad de dependencias externas como Composer.
