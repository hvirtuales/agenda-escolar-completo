<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Centro de ayuda: artículos de la Ayuda y base de conocimiento del asistente flotante (fuente única).
 * Mejorar un artículo aquí mejora la Ayuda y las respuestas del asistente a la vez.
 *
 * Cada artículo (clave = slug de la dirección ayuda/{slug}):
 * - category: grupo del índice (orden en $categories) · title · summary (1-2 frases; es lo primero que responde el asistente)
 * - audience: quién lo ve: all | teacher | student | guardian | admin | platform (ver HelpCenter::audiences())
 * - keywords: palabras con las que se busca (además del título y el texto) · steps: pasos numerados (opcional)
 * - body: párrafos adicionales (opcional) · tip: nota destacada (opcional) · link: [texto, ruta] para «Ir a…» (opcional)
 */
class HelpCenter extends BaseConfig
{
    public array $categories = ['Primeros pasos', 'Comunicaciones', 'Cuadernos', 'Actividades', 'Seguimiento', 'Biblioteca', 'Inteligencia artificial', 'Estudiantes y familias', 'Administración', 'Cuenta y seguridad', 'Consola de plataforma'];

    public array $articles = [
        /* ---------- Primeros pasos ---------- */
        'recorrido' => [
            'category' => 'Primeros pasos', 'title' => 'Recorrido por la plataforma', 'audience' => ['all'],
            'keywords' => ['empezar', 'menu', 'barra', 'inicio', 'donde', 'navegar', 'pantalla'],
            'summary'  => 'A la izquierda está el menú con las secciones de tu rol; arriba, el buscador, los avisos, la institución activa y tu cuenta.',
            'steps'    => ['Usa el menú lateral para cambiar de sección (Inicio, Mi agenda, Cuadernos, Actividades…).', 'Busca cualquier cosa con el buscador de la barra superior o con Ctrl + K.', 'Revisa los avisos en la campana.', 'En el menú de tu cuenta (arriba a la derecha) eliges el tema claro u oscuro, abres Mi perfil o cierras sesión.'],
            'tip'      => 'El menú lateral se puede contraer con «Contraer menú», al final del menú.',
        ],
        'cambiar-institucion' => [
            'category' => 'Primeros pasos', 'title' => 'Trabajar en varias instituciones', 'audience' => ['all'],
            'keywords' => ['institucion', 'colegio', 'cambiar', 'varias', 'volver a la plataforma'],
            'summary'  => 'Si perteneces a varias instituciones, cámbiala desde el selector de la barra superior; cada una tiene sus propios datos.',
            'steps'    => ['Pulsa el nombre de la institución en la barra superior.', 'Elige la institución a la que quieres entrar.'],
            'body'     => ['El superadministrador encuentra «Volver a la plataforma» como primera opción de ese selector, para regresar a la consola.'],
        ],
        'buscador' => [
            'category' => 'Primeros pasos', 'title' => 'Usar el buscador', 'audience' => ['all'],
            'keywords' => ['buscar', 'busqueda', 'encontrar', 'ctrl k'],
            'summary'  => 'El buscador de la barra superior (Ctrl + K) encuentra páginas, actividades, estudiantes y personas según tu rol.',
            'steps'    => ['Pulsa el buscador o Ctrl + K.', 'Escribe al menos dos letras.', 'Elige un resultado con el ratón o con las flechas y Enter.'],
        ],
        'asistente-flotante' => [
            'category' => 'Primeros pasos', 'title' => 'El asistente flotante', 'audience' => ['all'],
            'keywords' => ['asistente', 'mover', 'ocultar', 'reubicar', 'arrastrar', 'mostrar'],
            'summary'  => 'El asistente responde cómo usar la plataforma con base en esta Ayuda. Puedes moverlo a cualquier lugar y ocultarlo.',
            'steps'    => ['Pulsa la burbuja para abrir el chat y escribe tu pregunta.', 'Arrástrala para moverla (o usa Alt + flechas); recuerda dónde la dejaste.', 'Para ocultarla, usa el botón del ojo tachado del panel.', 'Para volver a verla: menú de tu cuenta › «Mostrar u ocultar el asistente».'],
        ],

        /* ---------- Comunicaciones ---------- */
        'mensajes' => [
            'category' => 'Comunicaciones', 'title' => 'Enviar y leer mensajes', 'audience' => ['all'],
            'keywords' => ['mensaje', 'mensajes', 'enviar', 'comunicado', 'circular', 'escribir a', 'masivo', 'bandeja'],
            'summary'  => 'En «Comunicaciones › Mensajes» lees lo que te envían. Docentes y directivos escriben a grupos, familias, roles o personas.',
            'steps'    => ['Abre «Comunicaciones» en el menú: la pestaña «Mensajes» es tu bandeja de recibidos.', 'Para escribir (docentes y directivos), pulsa «Nuevo mensaje».', 'Elige los destinatarios: por rol (solo directivos), por grupo (estudiantes o familias) o busca personas; verás a cuántas llegará.', 'Escribe el asunto y el mensaje; marca «Importante» si debe llegar aunque la persona haya apagado los avisos.', 'Pulsa «Enviar». En «Enviados» ves cuántos lo leyeron.'],
            'body'     => ['Los docentes escriben a los estudiantes y familias de sus grupos y a sus colegas; rectoría y coordinación, a toda la institución. Estudiantes y familias reciben, pero no envían.'],
            'link'     => ['Ir a Mensajes', 'mensajes'],
        ],
        'noticias' => [
            'category' => 'Comunicaciones', 'title' => 'Noticias y boletines', 'audience' => ['all'],
            'keywords' => ['noticia', 'noticias', 'boletin', 'publicar noticia', 'novedades', 'portada'],
            'summary'  => 'En «Comunicaciones › Noticias» ves los boletines de la institución. Rectoría y coordinación los publican con imagen y público.',
            'steps'    => ['Abre «Comunicaciones» y la pestaña «Noticias» para ver las publicadas para ti (las destacadas salen primero).', 'Directivos: pulsa «Nueva noticia», escribe título, resumen y contenido y sube una portada.', 'Elige para quién es (docentes, estudiantes, familias, directivos) y si es destacada.', 'Guárdala como borrador o pulsa «Publicar»: su público recibe un aviso.'],
            'link'     => ['Ir a Noticias', 'noticias'],
        ],
        'eventos' => [
            'category' => 'Comunicaciones', 'title' => 'Calendario de eventos', 'audience' => ['all'],
            'keywords' => ['evento', 'eventos', 'calendario', 'reunion de padres', 'acto', 'festivo', 'salida', 'semana cultural'],
            'summary'  => 'Reuniones, actos, salidas y festivos de la institución aparecen en «Mi agenda». Rectoría y coordinación los crean, editan y eliminan desde su agenda.',
            'steps'    => ['Abre «Mi agenda»: los eventos salen en el calendario y en la lista; elige uno para ver su detalle.', 'Directivos: pulsa «Nuevo evento» en la agenda y elige categoría, fecha, hora o «Todo el día», lugar y público. Para cambiarlo o eliminarlo, ábrelo en la agenda.', 'Al crearlo, su público recibe un aviso y lo ve en «Mi agenda».'],
            'link'     => ['Ir a Mi agenda', 'agenda'],
        ],

        'red-comunicaciones' => [
            'category' => 'Comunicaciones', 'title' => 'Comunicaciones de la red', 'audience' => ['all'],
            'keywords' => ['red', 'comunicado de la red', 'mensaje de la red', 'noticia de la red', 'evento de la red', 'varios colegios'],
            'summary'  => 'La dirección de una red envía mensajes a directivos y docentes, y publica noticias y eventos en sus colegios.',
            'steps'    => ['En la consola de la red, abre «Comunicaciones».', 'Pulsa «Nuevo mensaje», «Noticia» o «Evento».', 'Elige los colegios y para quién es: los mensajes van a rectoría, coordinación o docentes; las noticias y eventos pueden ser también para estudiantes y familias.', 'Envía. En el historial ves, por colegio, cuántos lo leyeron o a cuántas personas llegó.'],
            'body'     => ['En cada colegio llegan con la marca de la red y no se pueden editar ni eliminar allí. La red solo ve totales, nunca nombres de personas.'],
        ],

        /* ---------- Cuadernos ---------- */
        'cuaderno-paneles' => [
            'category' => 'Cuadernos', 'title' => 'Tu cuaderno y sus paneles', 'audience' => ['teacher'],
            'keywords' => ['cuaderno', 'paneles', 'secciones', 'asignatura', 'onenote'],
            'summary'  => 'Cada asignatura de cada grupo tiene un cuaderno con secciones y páginas, como un cuaderno digital.',
            'steps'    => ['En «Cuadernos», el panel izquierdo muestra tus asignaturas por categoría.', 'El segundo panel muestra las secciones y páginas del cuaderno elegido.', 'A la derecha está la página; los botones de la barra muestran u ocultan los paneles.'],
            'link'     => ['Ir a Cuadernos', 'cuadernos'],
        ],
        'crear-pagina' => [
            'category' => 'Cuadernos', 'title' => 'Crear y publicar una página', 'audience' => ['teacher'],
            'keywords' => ['pagina', 'crear', 'publicar', 'contenido', 'bloque', 'seccion', 'escribir'],
            'summary'  => 'Crea la página en una sección, escríbela con bloques y pulsa «Publicar» para que tus estudiantes la vean.',
            'steps'    => ['Abre el cuaderno de la asignatura en «Cuadernos».', 'Pulsa «+» junto a una sección para crear una página (o crea una sección nueva).', 'Escribe con bloques: texto, título, fórmula, imagen, nota destacada o separador.', 'Se guarda sola mientras escribes; el estado aparece en la barra.', 'Pulsa «Publicar» (o «Publicar cambios»). Los estudiantes ven siempre la última versión publicada.'],
            'link'     => ['Ir a Cuadernos', 'cuadernos'],
        ],
        'vista-estudiante' => [
            'category' => 'Cuadernos', 'title' => 'Ver la plataforma como un estudiante', 'audience' => ['teacher'],
            'keywords' => ['vista de estudiante', 'previsualizar', 'vista previa', 'como lo ve un estudiante', 'como ven los estudiantes'],
            'summary'  => '«Vista de estudiante» muestra cómo ve la plataforma un estudiante de un grupo real, sin datos de ningún estudiante.',
            'steps'    => ['En la barra de una página del cuaderno pulsa «Vista de estudiante».', 'Cambia de grupo con el selector de la barra de vista previa.', 'Vuelve con «Volver a la vista docente».'],
            'body'     => ['En la vista previa nada se guarda: puedes abrir retos y presentar evaluaciones de prueba, y el tutor de IA no responde.'],
        ],

        /* ---------- Actividades ---------- */
        'crear-reto' => [
            'category' => 'Actividades', 'title' => 'Crear un reto', 'audience' => ['teacher'],
            'keywords' => ['reto', 'crear', 'actividad', 'pasos', 'pistas', 'rubrica', 'asignar'],
            'summary'  => 'En «Actividades» pulsa «Nuevo reto», completa instrucciones, pasos, pistas y rúbrica, y publícalo con sus fechas.',
            'steps'    => ['Ve a «Actividades» y pulsa «Nuevo reto».', 'Elige la asignatura y el grupo y escribe el título.', 'Completa el objetivo, las instrucciones, los pasos, las pistas y el entregable.', 'Ajusta la rúbrica (los pesos suman 100 %) y el modo del tutor de IA.', 'Pulsa «Publicar y asignar» y elige la fecha de inicio y de cierre.'],
            'tip'      => '«Guardar como plantilla» deja el reto en la Biblioteca para reutilizarlo en otros grupos.',
            'link'     => ['Ir a Actividades', 'actividades'],
        ],
        'crear-evaluacion' => [
            'category' => 'Actividades', 'title' => 'Crear una evaluación', 'audience' => ['teacher'],
            'keywords' => ['evaluacion', 'examen', 'quiz', 'prueba', 'preguntas', 'intentos', 'tiempo'],
            'summary'  => 'En «Actividades» pulsa «Nueva evaluación», agrega preguntas o tráelas de un banco y publícala con tiempo límite e intentos.',
            'steps'    => ['Ve a «Actividades» y pulsa «Nueva evaluación».', 'Agrega preguntas: selección única o múltiple, verdadero o falso, numérica, corta o abierta.', 'O usa «Agregar desde un banco» o «Proponer con IA».', 'Define el tiempo límite, los intentos, si se mezclan las preguntas y cuándo se ven los resultados.', 'Pulsa «Publicar y asignar» con sus fechas.'],
            'body'     => ['Las preguntas abiertas se califican a mano en «Entregas»; las demás se califican solas. Durante la evaluación el tutor de IA no está disponible.'],
            'link'     => ['Ir a Actividades', 'actividades'],
        ],
        'revisar-entregas' => [
            'category' => 'Actividades', 'title' => 'Revisar y calificar entregas', 'audience' => ['teacher'],
            'keywords' => ['revisar', 'calificar', 'entregas', 'nota', 'rubrica', 'retroalimentacion', 'devolver'],
            'summary'  => 'En «Entregas» eliges la actividad y cada estudiante, marcas los niveles de la rúbrica, escribes la retroalimentación y guardas.',
            'steps'    => ['Ve a «Entregas» y abre la actividad.', 'Elige un estudiante de la lista.', 'Marca un nivel por criterio de la rúbrica: la nota se calcula sola.', 'Escribe la retroalimentación o usa «Proponer con IA» y ajústala.', 'Pulsa «Guardar calificación» o «Devolver para corrección».'],
            'tip'      => 'El estudiante y su acudiente reciben un aviso cuando calificas o devuelves.',
            'link'     => ['Ir a Entregas', 'entregas'],
        ],
        'mi-agenda' => [
            'category' => 'Actividades', 'title' => 'Mi agenda', 'audience' => ['all'],
            'keywords' => ['agenda', 'calendario', 'recordatorio', 'nota', 'tarea', 'fecha'],
            'summary'  => '«Mi agenda» reúne en un calendario los cierres de retos y evaluaciones, las notas de los docentes y tus recordatorios.',
            'steps'    => ['Abre «Mi agenda» en el menú.', 'Elige un día en el calendario para ver su detalle.', 'Pulsa «Nueva nota» o «Nuevo recordatorio» para agregar algo.', 'Si eres docente, elige un grupo en «Para»: sus estudiantes la verán y recibirán un aviso.', 'Marca las notas como hechas con el botón del círculo.'],
            'link'     => ['Ir a Mi agenda', 'agenda'],
        ],

        /* ---------- Seguimiento ---------- */
        'estudiantes-seguimiento' => [
            'category' => 'Seguimiento', 'title' => 'Seguimiento de estudiantes', 'audience' => ['teacher'],
            'keywords' => ['estudiantes', 'alertas', 'progreso', 'ficha', 'promedio', 'acompanamiento', 'observacion'],
            'summary'  => 'En «Seguimiento › Por grupo» ves el promedio, las entregas, el último acceso y las alertas de cada estudiante.',
            'steps'    => ['Ve a «Seguimiento», pestaña «Por grupo», y elige el grupo.', 'Revisa las alertas (vencidas sin entregar o promedio bajo).', 'Abre la ficha del estudiante para ver el detalle por asignatura, sus acudientes y tus observaciones.'],
            'body'     => ['Las observaciones de la ficha son internas: no las ven el estudiante ni su familia.'],
            'link'     => ['Ir a Estudiantes', 'estudiantes'],
        ],
        'reportes' => [
            'category' => 'Seguimiento', 'title' => 'Reportes y exportar notas', 'audience' => ['teacher'],
            'keywords' => ['reporte', 'exportar', 'excel', 'csv', 'notas', 'preguntas dificiles'],
            'summary'  => 'En «Seguimiento › Por asignatura» ves las notas por actividad de una asignatura y grupo, las preguntas más difíciles y descargas el CSV.',
            'steps'    => ['Ve a «Seguimiento», pestaña «Por asignatura», y elige la asignatura y el grupo.', 'Revisa la tabla de notas y las preguntas con más errores.', 'Pulsa «Exportar CSV»: se abre en Excel.'],
            'link'     => ['Ir a Reportes', 'reportes'],
        ],
        'docentes-seguimiento' => [
            'category' => 'Seguimiento', 'title' => 'Acompañar a los docentes', 'audience' => ['admin'],
            'keywords' => ['docentes', 'seguimiento', 'acompanar', 'revision atrasada', 'entregas sin revisar', 'carga', 'profesores'],
            'summary'  => 'Coordinación y rectoría tienen «Docentes»: la carga de cada docente, sus entregas por revisar y señales para acompañar.',
            'steps'    => ['Ve a «Docentes» en el menú.', 'Filtra «Solo los que tienen señales» para ver a quién acompañar.', 'Abre la ficha de un docente para ver sus asignaturas, pendientes y próximos cierres.'],
            'body'     => ['Es una herramienta de acompañamiento, no de evaluación del desempeño: no hay rankings ni comparaciones entre docentes.'],
            'link'     => ['Ir a Docentes', 'docentes'],
        ],

        /* ---------- Biblioteca ---------- */
        'bancos-preguntas' => [
            'category' => 'Biblioteca', 'title' => 'Bancos de preguntas', 'audience' => ['teacher'],
            'keywords' => ['banco', 'preguntas', 'biblioteca', 'aleatoria', 'reutilizar'],
            'summary'  => 'Los bancos guardan preguntas por asignatura, con tema y dificultad, para reutilizarlas en varias evaluaciones.',
            'steps'    => ['Ve a «Biblioteca › Bancos de preguntas» y pulsa «Nuevo banco de preguntas».', 'Agrega preguntas a mano, con IA o importándolas de una evaluación.', 'Úsalas desde el editor de evaluaciones con «Agregar desde un banco», o crea una evaluación aleatoria desde el banco.'],
            'link'     => ['Ir a la Biblioteca', 'biblioteca'],
        ],
        'plantillas' => [
            'category' => 'Biblioteca', 'title' => 'Plantillas', 'audience' => ['teacher'],
            'keywords' => ['plantilla', 'reutilizar', 'copiar', 'usar plantilla'],
            'summary'  => 'Guarda un reto, una evaluación o una página como plantilla y úsala para crear borradores en otras asignaturas.',
            'steps'    => ['En el editor de un reto o una evaluación pulsa «Guardar como plantilla» (en una página del cuaderno, el botón de plantilla de la barra).', 'Ve a «Biblioteca › Plantillas».', 'Pulsa «Usar plantilla», elige la asignatura y el grupo y crea el borrador.'],
            'link'     => ['Ir a Plantillas', 'biblioteca?t=plantillas'],
        ],
        'recursos' => [
            'category' => 'Biblioteca', 'title' => 'Recursos compartidos', 'audience' => ['teacher'],
            'keywords' => ['recurso', 'archivo', 'pdf', 'enlace', 'subir', 'documento', 'manual'],
            'summary'  => 'En «Biblioteca › Recursos» compartes archivos (PDF, Office, imágenes; hasta 20 MB) o enlaces con toda la institución.',
            'steps'    => ['Ve a «Biblioteca › Recursos» y pulsa «Nuevo recurso».', 'Elige «Archivo» o «Enlace».', 'Escribe el título, elige la asignatura (o General) y guarda.'],
            'link'     => ['Ir a Recursos', 'biblioteca?t=recursos'],
        ],

        /* ---------- Inteligencia artificial ---------- */
        'ia-como-funciona' => [
            'category' => 'Inteligencia artificial', 'title' => 'Cómo funciona la IA y sus límites', 'audience' => ['all'],
            'keywords' => ['ia', 'inteligencia artificial', 'como funciona', 'limites', 'borrador', 'datos'],
            'summary'  => 'La IA ayuda a preparar contenido y a acompañar a los estudiantes, pero no decide: todo lo que propone es un borrador que una persona revisa.',
            'body'     => ['Solo usa información de tu institución que puedes ver: la página o actividad en la que estás. Nunca ve otros grupos ni otras instituciones.', 'Las respuestas generadas por IA se marcan como tales. Durante las evaluaciones no está disponible.'],
            'tip'      => 'Cada consulta consume parte del cupo de IA; lo ves debajo del campo de pregunta. Si se agota, la IA se pausa y el resto de la plataforma sigue funcionando.',
        ],
        'ia-modos' => [
            'category' => 'Inteligencia artificial', 'title' => 'Modos del tutor en los retos', 'audience' => ['teacher', 'student'],
            'keywords' => ['tutor', 'modo', 'guia', 'explicacion', 'pistas'],
            'summary'  => 'En cada reto el docente elige el modo del tutor: Guía (preguntas y pistas, sin respuesta final), Explicación (conceptos con ejemplos) o Sin IA.',
            'body'     => ['El estudiante usa el tutor en el panel del reto y en sus cuadernos («Tutor» en la barra de la página), donde le explica la página, le da ejemplos o le propone ejercicios para practicar.'],
        ],
        'ia-docente' => [
            'category' => 'Inteligencia artificial', 'title' => 'IA para docentes', 'audience' => ['teacher'],
            'keywords' => ['copiloto', 'asistente del cuaderno', 'proponer con ia', 'generar preguntas', 'ia docente', 'ia en mis clases', 'usar la ia', 'uso la ia'],
            'summary'  => 'El asistente del cuaderno propone retos, preguntas, rúbricas y mejoras sobre la página abierta; «Proponer con IA» redacta preguntas y retroalimentación.',
            'steps'    => ['En una página del cuaderno pulsa «Asistente IA» y elige una sugerencia o escribe tu pedido.', 'En el editor de evaluaciones usa «Proponer con IA» para generar preguntas.', 'En «Entregas», «Proponer con IA» redacta la retroalimentación según los niveles que elegiste.'],
            'tip'      => 'Revisa siempre lo que propone la IA antes de publicarlo o guardarlo.',
        ],
        'ia-cupos' => [
            'category' => 'Inteligencia artificial', 'title' => 'Cupos de IA de la institución', 'audience' => ['admin'],
            'keywords' => ['cupo', 'tokens', 'consumo', 'reparto', 'ajustes ia', 'limite'],
            'summary'  => 'Cada institución tiene un cupo de IA mensual según su plan, repartido por rol; se configura en «Ajustes › IA».',
            'steps'    => ['Ve a «Ajustes › IA».', 'Ajusta el reparto entre docentes, coordinación y estudiantes y los topes diarios por persona.', 'Concede cupo adicional temporal a una persona o a un rol si lo necesita.'],
            'link'     => ['Ir a Ajustes › IA', 'ajustes/ia'],
        ],

        /* ---------- Estudiantes y familias ---------- */
        'estudiante-actividades' => [
            'category' => 'Estudiantes y familias', 'title' => 'Mis actividades y mi progreso', 'audience' => ['student'],
            'keywords' => ['mis actividades', 'tareas', 'entregar', 'progreso', 'nota', 'pendiente', 'reto'],
            'summary'  => 'En «Mis actividades» ves lo que tienes por hacer, lo entregado y lo calificado; en «Inicio», tu avance por asignatura.',
            'steps'    => ['Abre «Mis actividades» y elige la pestaña: Por hacer, Entregadas, Calificadas o Vencidas.', 'Abre un reto, sigue los pasos, escribe tu respuesta y pulsa «Entregar».', 'Mira tus notas y la retroalimentación de tus docentes en «Inicio».'],
            'link'     => ['Ir a Mis actividades', 'estudiante/actividades'],
        ],
        'estudiante-asignaturas' => [
            'category' => 'Estudiantes y familias', 'title' => 'Mis asignaturas', 'audience' => ['student'],
            'keywords' => ['mis asignaturas', 'cuaderno', 'clase', 'leer', 'paginas'],
            'summary'  => 'En «Mis asignaturas» lees las páginas que publican tus docentes, organizadas por secciones.',
            'steps'    => ['Abre «Mis asignaturas» y elige la asignatura.', 'Elige la sección y la página.', 'Si no entiendes algo, abre el «Tutor» de la página.'],
            'link'     => ['Ir a Mis asignaturas', 'estudiante/asignaturas'],
        ],
        'acudientes' => [
            'category' => 'Estudiantes y familias', 'title' => 'Para las familias (acudientes)', 'audience' => ['guardian', 'admin'],
            'keywords' => ['acudiente', 'familia', 'padre', 'madre', 'hijo', 'hija', 'hijos', 'mi hijo', 'mi hija', 'agenda de mi hijo', 'vincular'],
            'summary'  => 'Las familias ven en solo lectura el progreso, las actividades y la agenda de sus estudiantes vinculados.',
            'steps'    => ['En «Mis estudiantes» abre la ficha de tu estudiante.', 'Revisa su promedio, lo que tiene por hacer y lo vencido.', 'En «Agenda» ves sus fechas y las notas de sus docentes.'],
            'body'     => ['La institución vincula a cada acudiente desde la ficha del estudiante («Seguimiento» › ficha del estudiante › Acudientes). Cada acudiente ve únicamente a sus estudiantes.'],
            'link'     => ['Ir a Mis estudiantes', 'acudiente'],
        ],

        /* ---------- Administración ---------- */
        'estructura-academica' => [
            'category' => 'Administración', 'title' => 'Estructura académica', 'audience' => ['admin'],
            'keywords' => ['estructura', 'grupo', 'nivel', 'asignatura', 'periodo', 'matricular', 'sede', 'categoria'],
            'summary'  => 'En «Ajustes › Estructura académica» defines periodos, niveles, grupos, categorías y asignaturas, matriculas estudiantes y asignas docentes.',
            'steps'    => ['Ve a «Ajustes › Estructura académica».', 'Crea el periodo, los niveles y los grupos.', 'Crea las categorías y asignaturas y ofrécelas en cada grupo.', 'Abre un grupo para matricular estudiantes y asigna docentes a cada asignatura.'],
            'link'     => ['Ir a Estructura', 'ajustes/estructura'],
        ],
        'usuarios' => [
            'category' => 'Administración', 'title' => 'Agregar personas y asignar roles', 'audience' => ['admin'],
            'keywords' => ['usuario', 'persona', 'agregar', 'invitar', 'rol', 'permiso', 'crear cuenta'],
            'summary'  => 'En «Ajustes › Usuarios» agregas personas con su rol, sede y vigencia, y editas su acceso.',
            'steps'    => ['Ve a «Ajustes › Usuarios» y pulsa «Agregar persona».', 'Escribe el nombre, el correo, el rol y una contraseña inicial (se le pedirá cambiarla al entrar).', 'Edita su rol, sede, vigencia o estado con el botón de editar.'],
            'link'     => ['Ir a Usuarios', 'ajustes/usuarios'],
        ],
        'contrasena-temporal' => [
            'category' => 'Administración', 'title' => 'Ayudar a alguien que olvidó su contraseña', 'audience' => ['admin'],
            'keywords' => ['olvido', 'contrasena temporal', 'restablecer', 'perdio celular', 'quitar verificacion'],
            'summary'  => 'En «Ajustes › Usuarios», el botón de llave asigna una contraseña temporal; el de escudo quita la verificación en dos pasos.',
            'steps'    => ['Ve a «Ajustes › Usuarios» y busca a la persona.', 'Pulsa el botón de llave y confirma: verás la contraseña temporal una sola vez.', 'Entrégasela por un medio seguro; al entrar se le pedirá cambiarla.', 'Si perdió el celular, pulsa el botón de escudo para quitar su verificación en dos pasos.'],
            'body'     => ['Solo puedes hacerlo con personas que pertenecen únicamente a tu institución; si están en otra, lo hace el superadministrador.'],
            'link'     => ['Ir a Usuarios', 'ajustes/usuarios'],
        ],

        /* ---------- Cuenta y seguridad ---------- */
        'mi-perfil' => [
            'category' => 'Cuenta y seguridad', 'title' => 'Mi perfil y accesibilidad', 'audience' => ['all'],
            'keywords' => ['perfil', 'foto', 'nombre', 'texto', 'tamano', 'accesibilidad', 'oscuro', 'tema', 'voz'],
            'summary'  => 'En «Mi perfil» cambias tu nombre y foto, el tamaño del texto, reduces el movimiento y activas la lectura en voz alta del tutor.',
            'steps'    => ['Abre el menú de tu cuenta y pulsa «Mi perfil».', 'Cambia tus datos y preferencias.', 'Pulsa «Guardar cambios».'],
            'body'     => ['El tema claro u oscuro se elige en el menú de tu cuenta.'],
            'link'     => ['Ir a Mi perfil', 'perfil'],
        ],
        'avisos' => [
            'category' => 'Cuenta y seguridad', 'title' => 'Avisos y notificaciones', 'audience' => ['all'],
            'keywords' => ['aviso', 'notificacion', 'campana', 'correo', 'alerta'],
            'summary'  => 'Los avisos llegan a la campana de la barra superior; en «Mi perfil › Avisos» eliges qué temas recibir.',
            'steps'    => ['Pulsa la campana para ver tus avisos recientes.', 'Abre un aviso para ir a lo que corresponde.', 'En «Mi perfil › Avisos» activa o desactiva cada tema en la plataforma y por correo.'],
            'link'     => ['Ir a Mi perfil', 'perfil'],
        ],
        'contrasena' => [
            'category' => 'Cuenta y seguridad', 'title' => 'Cambiar mi contraseña', 'audience' => ['all'],
            'keywords' => ['contrasena', 'clave', 'cambiar', 'olvide'],
            'summary'  => 'En «Mi perfil › Contraseña» escribes tu contraseña actual y la nueva (mínimo 10 caracteres).',
            'steps'    => ['Abre «Mi perfil».', 'En «Contraseña», escribe la actual y dos veces la nueva.', 'Pulsa «Cambiar contraseña».'],
            'body'     => ['Si la olvidaste, pídele al administrador de tu institución una contraseña temporal.'],
            'link'     => ['Ir a Mi perfil', 'perfil#seguridad'],
        ],
        'dos-pasos' => [
            'category' => 'Cuenta y seguridad', 'title' => 'Verificación en dos pasos', 'audience' => ['all'],
            'keywords' => ['dos pasos', 'verificacion', 'autenticador', '2fa', 'codigo', 'qr', 'respaldo'],
            'summary'  => 'Además de la contraseña, al entrar se pide un código de una aplicación autenticadora. Es obligatoria para administradores.',
            'steps'    => ['Instala una aplicación autenticadora (Google Authenticator, Microsoft Authenticator u otra).', 'En «Mi perfil › Verificación en dos pasos» pulsa «Activar».', 'Escanea el código QR y escribe el código de 6 dígitos.', 'Guarda los códigos de respaldo: sirven si pierdes el celular.'],
            'link'     => ['Ir a Mi perfil', 'perfil#dos-pasos'],
        ],

        /* ---------- Consola de plataforma ---------- */
        'consola-instituciones' => [
            'category' => 'Consola de plataforma', 'title' => 'Instituciones, planes y solicitudes', 'audience' => ['platform'],
            'keywords' => ['consola', 'institucion', 'plan', 'solicitud', 'demostracion', 'crear institucion'],
            'summary'  => 'En la consola creas instituciones con su plan y administrador, gestionas planes y atiendes las solicitudes de demostración.',
            'steps'    => ['En «Instituciones» pulsa «Nueva institución» (o «Crear institución» desde una solicitud).', 'En «Planes» defines los cupos de cada plan.', 'En «Solicitudes» gestionas las que llegan desde la página pública.', 'Con «Entrar» abres una institución como superadministrador.'],
            'link'     => ['Ir a Instituciones', 'plataforma/instituciones'],
        ],
        'consola-configuracion' => [
            'category' => 'Consola de plataforma', 'title' => 'Configuración y asistente', 'audience' => ['platform'],
            'keywords' => ['configuracion', 'asistente', 'imagen', 'gif', 'correo', 'proveedor', 'auditoria'],
            'summary'  => 'En «IA» configuras la clave del proveedor y ves modelos y consumo; en «Configuración», el correo, la base de datos y el nombre e imagen (o GIF) del asistente.',
            'steps'    => ['Ve a «Configuración».', 'En «Asistente de la interfaz» cambia el nombre, sube una imagen o GIF de hasta 2 MB y guarda.', 'Revisa la «Auditoría» para ver las acciones sensibles de toda la plataforma.'],
            'link'     => ['Ir a Configuración', 'plataforma/configuracion'],
        ],
    ];
}
