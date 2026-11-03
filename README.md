<div align="center">

  <h1>🏗️ FAMEP — Control de Producción y Calidad</h1>

  <p>
    <b>Sistema Monolítico de Gestión, Control y Seguimiento Operativo en Taller de Estructuras Metálicas</b>
  </p>

  <p>
    <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-13.30.1-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13"></a>
    <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3"></a>
    <a href="https://www.postgresql.org/"><img src="https://img.shields.io/badge/PostgreSQL-18--alpine-4169E1?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL 18"></a>
    <a href="https://www.docker.com/"><img src="https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker"></a>
    <a href="https://tailwindcss.com/"><img src="https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="Tailwind CSS"></a>
    <a href="https://n8n.io/"><img src="https://img.shields.io/badge/n8n-Workflow-FF6C37?style=for-the-badge&logo=n8n&logoColor=white" alt="n8n"></a>
    <a href="https://whatsapp.com"><img src="https://img.shields.io/badge/WhatsApp-Evolution_API-25D366?style=for-the-badge&logo=whatsapp&logoColor=white" alt="WhatsApp"></a>
  </p>

  <p>
    <a href="#-resumen-ejecutivo">Resumen</a> •
    <a href="#%EF%B8%8F-stack-tecnol%C3%B3gico">Stack</a> •
    <a href="#-puertos-y-servicios">Puertos</a> •
    <a href="#-m%C3%B3dulos-del-sistema">Módulos</a> •
    <a href="#-despliegue-y-operaci%C3%B3n">Despliegue</a> •
    <a href="#-suite-de-pruebas">Pruebas</a>
  </p>

</div>

<hr />

<h2>📋 Resumen Ejecutivo</h2>

<p>
  La plataforma web <b>FAMEP</b> es una solución independiente diseñada para digitalizar, supervisar y controlar el flujo de producción de estructuras metálicas en taller. El sistema actúa como el núcleo operativo centralizado de la empresa, permitiendo el desglose de proyectos en piezas/marcas, el seguimiento de fases productivas (Armado, Soldadura y Pintura), la emisión de dictámenes de calidad con evidencias fotográficas y la transmisión de alertas automatizadas a través de WhatsApp.
</p>

<p>
  Asimismo, incluye un portal público de consulta externa donde los clientes pueden verificar el estado y avance en tiempo real de sus obras mediante un código único de acceso, garantizando transparencia e inmutabilidad en la información.
</p>

<hr />

<h2>🛠️ Stack Tecnológico</h2>

<table width="100%">
  <thead>
    <tr>
      <th align="left">Capa</th>
      <th align="left">Tecnología</th>
      <th align="left">Versión / Especificación</th>
      <th align="left">Notas de Implementación</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><b>Backend</b></td>
      <td>PHP / Laravel</td>
      <td><code>PHP 8.3</code> / <code>Laravel 13.30.1</code></td>
      <td>Arquitectura SSR con Blade y controladores optimizados.</td>
    </tr>
    <tr>
      <td><b>Base de Datos</b></td>
      <td>PostgreSQL</td>
      <td><code>18-alpine</code></td>
      <td>Esquema inicial en <code>docker/postgres/init/01-inicial.sql</code>.</td>
    </tr>
    <tr>
      <td><b>Servidor Web</b></td>
      <td>Nginx + PHP-FPM</td>
      <td><code>nginx:alpine</code></td>
      <td>Contenedor optimizado con alias directo para evidencias.</td>
    </tr>
    <tr>
      <td><b>Frontend</b></td>
      <td>Blade + Tailwind + Alpine.js</td>
      <td>Locales en <code>public/vendor/</code></td>
      <td>100% Offline/Local, sin CDN externas ni compilación Vite en runtime.</td>
    </tr>
    <tr>
      <td><b>Gráficas</b></td>
      <td>Chart.js</td>
      <td><code>v4.4.4</code></td>
      <td>Renderizado local de KPIs de avance del taller y obras.</td>
    </tr>
    <tr>
      <td><b>Automatización</b></td>
      <td>n8n</td>
      <td>Docker Image Official</td>
      <td>Recepción de webhooks de alerta de calidad (puerto 5678).</td>
    </tr>
    <tr>
      <td><b>Mensajería</b></td>
      <td>Evolution API</td>
      <td><code>v1.8.2</code></td>
      <td>Motor Baileys para despacho de WhatsApp (puerto 9091).</td>
    </tr>
    <tr>
      <td><b>Testing</b></td>
      <td>PHPUnit Harness Custom</td>
      <td>13 Suites / 453 Checks</td>
      <td>Pruebas aisladas con transacciones y rollback automático.</td>
    </tr>
  </tbody>
</table>

<hr />

<h2>🔌 Puertos y Servicios (Infraestructura Docker)</h2>

<p>
  El sistema se ejecuta de manera totalmente aislada mediante <b>Docker Compose</b>, organizando los siguientes servicios e interfaces de red:
</p>

<table width="100%">
  <thead>
    <tr>
      <th align="center">Servicio</th>
      <th align="center">Contenedor</th>
      <th align="center">Puerto Host</th>
      <th align="center">Puerto Contenedor</th>
      <th align="left">Descripción</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td align="center"><b>Web App</b></td>
      <td align="center"><code>famep-web</code></td>
      <td align="center"><code>9090</code></td>
      <td align="center"><code>80</code></td>
      <td align="left">Servidor Nginx (Interfaz Principal de la Aplicación)</td>
    </tr>
    <tr>
      <td align="center"><b>Evolution API</b></td>
      <td align="center"><code>famep-evolution</code></td>
      <td align="center"><code>9091</code></td>
      <td align="center"><code>8080</code></td>
      <td align="left">API de WhatsApp Manager e Instancia <code>famep-wa</code></td>
    </tr>
    <tr>
      <td align="center"><b>Adminer</b></td>
      <td align="center"><code>famep-adminer</code></td>
      <td align="center"><code>9092</code></td>
      <td align="center"><code>8080</code></td>
      <td align="left">Gestor GUI de PostgreSQL (Solo perfil <code>dev</code>)</td>
    </tr>
    <tr>
      <td align="center"><b>PostgreSQL</b></td>
      <td align="center"><code>famep-db</code></td>
      <td align="center"><code>5433</code></td>
      <td align="center"><code>5432</code></td>
      <td align="left">Motor de Base de Datos PostgreSQL 18</td>
    </tr>
    <tr>
      <td align="center"><b>n8n Engine</b></td>
      <td align="center"><code>famep-n8n</code></td>
      <td align="center"><code>5678</code></td>
      <td align="center"><code>5678</code></td>
      <td align="left">Motor de Automatización de Webhooks y Workflows</td>
    </tr>
  </tbody>
</table>

<hr />

<h2>📦 Módulos del Sistema</h2>

<ul>
  <li>
    <b>Jefatura de Producción:</b> Registro de obras, desglose de marcas/piezas (peso, cantidad, tipo de estructura), asignación de operarios habilitados, gestión de estatus de fase (<i>En Proceso</i>, <i>Completada</i>) y consulta del historial.
  </li>
  <li>
    <b>Jefatura de Calidad:</b> Emisión de dictámenes técnicos (<i>Aprobado</i>, <i>Rechazado</i>, <i>Con Observaciones / Enviado a Reproceso</i>), carga de evidencias fotográficas por fase e inmutabilidad de dictámenes en fases concluidas.
  </li>
  <li>
    <b>Alertas Automatizadas (RF-33):</b> Notificación inmediata por WhatsApp vía n8n + Evolution API cuando un dictamen es marcado como <i>Rechazado</i> o <i>Enviado a Reproceso</i>.
  </li>
  <li>
    <b>Portal del Cliente:</b> Consulta pública en <code>/consulta</code> mediante código de acceso único (<code>FAMEP-XXXX</code>). Vista panorámica del avance de la obra y desglose por marca sin requerir sesión ni permitir modificaciones.
  </li>
  <li>
    <b>Gestión de Operarios:</b> Control de habilitación multifase (N:M), derivación automática de especialidad y bloqueo de asignaciones incompatibles en taller.
  </li>
  <li>
    <b>Reportes y Bitácora de Auditoría:</b> Análisis de productividad, avance general, hallazgos de calidad y registro automatizado de escrituras con valores previos/nuevos (<code>logs_auditoria</code>) con exportación a formato CSV.
  </li>
</ul>

<hr />

<h2>🚀 Despliegue y Operación</h2>

<h3>1. Clonar el repositorio y preparar variables</h3>

<pre><code>git clone https://github.com/TU_USUARIO/famep-app.git
cd famep-app
cp .env.docker.example .env.docker
</code></pre>

<h3>2. Levantar el Entorno de Desarrollo (Dev)</h3>

<pre><code># Iniciar contenedores en segundo plano
docker compose up -d

# Limpiar cachés iniciales dentro de la aplicación
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
</code></pre>

<p>
  Acceso local: <b><a href="http://localhost:9090">http://localhost:9090</a></b>
</p>

<h3>3. Levantar el Entorno de Producción (Prod)</h3>

<pre><code># Construir la imagen de producción empaquetada
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
</code></pre>

<hr />

<h2>🧪 Suite de Pruebas (Harness Custom)</h2>

<p>
  El proyecto implementa un arnés de pruebas automatizado que ejecuta suites sobre la base de datos PostgreSQL real dentro de transacciones aisladas con <code>rollback</code> automático, garantizando que el entorno no sufra contaminación de datos ni efectúe envíos reales a WhatsApp durante las pruebas.
</p>

<pre><code># Ejecución de suites individuales
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/flujo.php
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/whatsapp_flujo.php
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/dictamenes_gestion.php
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/reportes_gestion.php
</code></pre>

<p align="center">
  <b>Resultado del arnés de pruebas:</b> <code>13 Suites / 453 OK / 0 FAIL</code>
</p>

<hr />

<h2>⚙️ Convenciones Principales</h2>

<ul>
  <li><b>Base de Datos:</b> La fuente de verdad del esquema reside en <code>docker/postgres/init/01-inicial.sql</code>. No se utilizan migraciones de Laravel para el dominio.</li>
  <li><b>Zona Horaria:</b> La base de datos y PHP procesan todo cálculo en <code>UTC</code>. La presentación visual, reportes, descargas CSV y alertas convierten las fechas a <code>America/Mexico_City</code> mediante la clase helper <code>App\Support\FechaLocal</code>.</li>
  <li><b>Seguridad y Archivos Sensibles:</b> Los archivos <code>.env</code> y <code>.env.docker</code> están totalmente excluidos de Git a través de la configuración estricta del <code>.gitignore</code>.</li>
</ul>

<hr />

<div align="center">
  <p>© 2026 FAMEP — Sistema de Control de Producción y Calidad de Estructuras Metálicas</p>
</div>