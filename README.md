# 🏢 ScaQR V15 - Enterprise Access Control System

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Dual_DB-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Kiosk_Engine-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

ScaQR V15 es la plataforma integral de control de accesos desarrollada para las plantas industriales de Fruitex (Jardín, Sauces y Partidas). Sustituye los registros manuales por un flujo digital automatizado mediante tecnología QR, integrando un panel de administración web, kioskos de escaneo con cámara en tiempo real y una bitácora inmutable para auditoría.

> **Nota de Privacidad:** Este proyecto es software propietario de uso interno corporativo. Este repositorio funciona como un *Showcase* técnico de la arquitectura, el modelado de datos y las soluciones backend implementadas.

## 🧠 Arquitectura y Soluciones Técnicas

* **Conexión de Base de Datos Dual:** Arquitectura diseñada para convivir con sistemas heredados, utilizando una conexión principal `sca` (esquema propio) y una conexión de solo lectura `sitec` para sincronizar en vivo la identidad, puestos y turnos desde el sistema de Recursos Humanos.
* **Motor de Reglas de Acceso (`AccesoService`):** Lógica de negocio de alta complejidad que evalúa en tiempo real tolerancias de horario, validación de turnos nocturnos cruzados con la medianoche, continuidad de mapeo de plantas y permisos activos, resolviendo accesos sin realizar escrituras prematuras en la base de datos.
* **Máquina de Estados en Puerta (Kiosko):** Frontend reactivo construido en JavaScript puro que gestiona el hardware de la cámara (`jsQR` a ~10fps con zoom dinámico), implementando *polling* asíncrono para mantener sincronizado el estado de la puerta con las resoluciones en vivo del guardia.
* **Expiración Lazy y Tareas Programadas:** Combinación de *accessors* puros en Eloquent para la capa de presentación y un *Cron Job* (`sca:expirar-solicitudes`) que corre cada minuto para invalidar QRs y permisos de forma persistente, con salvaguardas algorítmicas para proteger a los usuarios que aún se encuentran dentro de las instalaciones.
* **Generación Documental Autónoma:** Uso de `Browsershot` (Chrome headless) para renderizar credenciales temporales con códigos QR en alta resolución (PNG) y matrices de impresión en formato PDF, distribuidas automáticamente mediante servicios SMTP.
* **Seguridad y Auditoría:** Middleware estricto de roles (Superadmin, Admin, Aprobador, Empleado, Guardia), encriptación de sesiones para los kioskos públicos, 5 niveles de *scoping* de datos por planta y registro de auditoría inmutable de todas las decisiones operativas[cite: 1, 2].

## 💻 Stack Tecnológico

* **Backend:** PHP 8.3 estricto, Laravel 13.8.
* **Base de Datos:** MySQL (Diseño relacional avanzado con llaves lógicas para el cruce de esquemas aislados).
* **Frontend:** Blade, Tailwind CSS 4, Vite 8, JavaScript (Vanilla para el motor de escaneo de los kioskos).
* **Integraciones:** `simplesoftwareio/simple-qrcode`, `spatie/browsershot`[cite: 2].

## 🚀 Mi Rol en el Proyecto

Como **Full Stack Developer** e Ingeniero de Sistemas del proyecto, diseñé e implementé la solución *end-to-end*, abarcando desde la estructuración de la base de datos y la creación de los micro-servicios internos (`PresenciaService`, `BitacoraAccesoService`), hasta el desarrollo de las interfaces de usuario segmentadas para los cinco roles operativos. 

Para garantizar un ciclo de desarrollo ágil y entregas de nivel Enterprise, potencio mi flujo de trabajo utilizando **Inteligencia Artificial**. Esta integración me permite acelerar la refactorización de código, optimizar consultas SQL complejas (como los filtros correlacionados de la bitácora) y diseñar arquitecturas altamente escalables y documentadas.
