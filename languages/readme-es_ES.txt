=== Easy 2FA ===
Contributors: rebelcode
Tags: two-factor, 2fa, passkeys, authentication, security
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Claves de acceso, aplicaciones de autenticación, códigos de respaldo y 2FA por correo, con obligatoriedad por perfil y una recuperación que de verdad funciona. Todo gratis.

== Description ==

Easy 2FA añade un segundo paso a los inicios de sesión de WordPress y ofrece gratis todo lo que un sitio necesita para estar bien protegido. Claves de acceso, aplicaciones de autenticación, códigos de respaldo, obligatoriedad por perfil y una recuperación que de verdad devuelve el acceso a quien se ha quedado fuera. Nada de esto se reserva para una versión de pago.

La mayoría de los complementos de 2FA esconden la obligatoriedad por perfil detrás de un muro de pago, limitan la versión gratuita a un puñado de usuarios, o se distribuyen como una pieza más de una suite de seguridad pesada. Easy 2FA hace una sola cosa y la hace por completo.

**Métodos**

* Claves de acceso (WebAuthn): inicia sesión con Face ID, Touch ID, Windows Hello o una llave de seguridad física. Es el método que el asistente de configuración recomienda primero.
* Aplicación de autenticación (TOTP): funciona con Google Authenticator, 1Password, Authy y cualquier aplicación compatible con RFC 6238.
* Códigos de respaldo: diez códigos de un solo uso, generados automáticamente la primera vez que configuras cualquier método, para que nunca dependas de un móvil perdido para entrar.
* Códigos por correo: un código de seis dígitos enviado al correo de tu cuenta, como alternativa para quien no configure nada más.

**Obligatoriedad**

* Exige la 2FA por perfil, o a cualquier usuario con una capacidad concreta.
* Establece un período de gracia para que los usuarios existentes tengan tiempo de configurarla en lugar de quedarse fuera en el siguiente inicio de sesión.
* Una columna «2FA» en la pantalla de Usuarios muestra quién la ha configurado y quién no.

**Recuperación**

Quedarse fuera es la razón por la que mucha gente nunca activa la 2FA. Easy 2FA se lo toma en serio:

* Los códigos de respaldo se generan y se muestran al configurar el primer método, no como una idea de última hora.
* Cualquier administrador puede restablecer la 2FA de otro usuario desde la pantalla de Usuarios.
* `wp 2fa reset <usuario>` restablece a un usuario desde la línea de comandos cuando nadie puede entrar al escritorio.

**Contraseñas de aplicación**

La 2FA no se aplica a las contraseñas de aplicación, que autentican la API REST y XML-RPC. Easy 2FA lo indica con claridad en su pantalla de ajustes y te permite desactivar las contraseñas de aplicación por perfil si quieres cerrar esa vía.

== Installation ==

1. Instálalo desde Plugins → Añadir nuevo y busca «Easy 2FA», o sube los archivos del complemento a `/wp-content/plugins/easy-2fa/`.
2. Actívalo desde el menú Plugins.
3. Ve a Usuarios → Configuración de doble factor y configura tu primer método. Guarda los códigos de respaldo que te muestre.
4. Para exigir la 2FA a otros usuarios, abre Ajustes → Easy 2FA y elige los perfiles y el período de gracia.

== Frequently Asked Questions ==

= ¿Qué pasa si pierdo el móvil y me quedo fuera? =

Usa uno de los códigos de respaldo que se mostraron al configurar la 2FA. Si no los guardaste, otro administrador puede restablecer tu cuenta desde la pantalla de Usuarios. Si no puede entrar nadie, quien tenga acceso al servidor ejecuta `wp 2fa reset <tu-usuario>` y tu segundo factor queda eliminado.

= ¿Necesito una cuenta o un plan de pago para las claves de acceso o la obligatoriedad? =

No. Las claves de acceso, la obligatoriedad por perfil, los códigos de respaldo y la recuperación están todos en el complemento gratuito. No hay ninguna cuenta que crear y nada se envía a servidores externos.

= ¿Funciona con las contraseñas de aplicación, la API REST y XML-RPC? =

Las contraseñas de aplicación se saltan la 2FA por diseño: así es como WordPress autentica las peticiones automatizadas. Easy 2FA lo documenta en su pantalla de ajustes y te permite desactivar las contraseñas de aplicación por perfil si prefieres cerrar esa vía.

= ¿Qué versión de PHP necesito para las claves de acceso? =

Las claves de acceso necesitan PHP 8.0 o posterior. Con una versión anterior de PHP el complemento sigue funcionando y ofrece aplicaciones de autenticación, códigos de respaldo y correo; solo se oculta el método de clave de acceso.

= ¿Puedo exigir la 2FA solo a los administradores? =

Sí. En Ajustes → Easy 2FA puedes elegir exactamente qué perfiles quedan obligados y establecer un período de gracia para que se avise a la gente en lugar de dejarla fuera de inmediato.

== Changelog ==

= 0.1.0 =
* Primera versión: claves de acceso, aplicaciones de autenticación, códigos de respaldo y códigos por correo.
* Obligatoriedad por perfil con un período de gracia configurable.
* Recuperación ante bloqueos: códigos de respaldo, restablecimiento por administrador y un comando de restablecimiento por WP-CLI.
* Control de las contraseñas de aplicación por perfil.
