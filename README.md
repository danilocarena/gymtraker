# DayTraker Pro

Aplicación web para la organización diaria y seguimiento de productividad. Permite gestionar tareas, diseñar plantillas maestras y analizar tu enfoque diario.

## Características Principales

* Autenticación local y mediante Google Identity Services.
* Gestión de plantillas de planes diarios.
* Registro detallado de tareas con prioridad, tiempo y estado.
* Historial de productividad con visualización de eficiencia.
* Seguimiento de puntaje de enfoque diario.


## Requisitos del Sistema

* Servidor web (Apache recomendado).
* PHP 7.4 o superior.
* MySQL 5.7 o MariaDB 10.3 o superior.
* Soporte para mod_rewrite habilitado en Apache.

## Instalación

1. Clonar o subir los archivos al servidor web.
2. Importar el archivo `daytracker.sql` en su base de datos MySQL.
3. Crear un archivo `.env` en la raíz del proyecto basado en el archivo `.env.example`.
4. Configurar las credenciales de la base de datos en el archivo `.env`.

### Configuración de Google Auth

Para habilitar el inicio de sesión con Google:
1. Crear un proyecto en Google Cloud Console.
2. Configurar la pantalla de consentimiento OAuth.
3. Crear un ID de cliente OAuth 2.0.
4. Añadir el dominio de la aplicación en orígenes de JavaScript autorizados.
5. Copiar el Client ID en la variable `GOOGLE_CLIENT_ID` del archivo `.env`.

## Seguridad

* El acceso externo al archivo `.env` y a scripts de configuración está bloqueado mediante `.htaccess`.
* Se recomienda utilizar un usuario de base de datos con permisos limitados (SELECT, INSERT, UPDATE, DELETE) para la aplicación en producción.
* Las contraseñas se almacenan de forma segura utilizando password_hash de PHP.

## Notas de Desarrollo

La aplicación utiliza un cargador de entorno personalizado ubicado en `includes/env_loader.php` para gestionar la configuración sin necesidad de dependencias externas como Composer.
