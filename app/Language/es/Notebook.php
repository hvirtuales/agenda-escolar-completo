<?php

/** Bloques del cuaderno (editor del docente y vista del estudiante). */
return [
    'menuContent' => 'Contenido', 'menuSoon' => 'Llegan con otros módulos', 'menuAi' => 'Crear con IA',
    // Nombres (menú «Agregar bloque») y su forma con artículo (acciones: «Subir el párrafo»)
    'bText' => 'Texto', 'bHeading' => 'Título', 'bCallout' => 'Nota', 'bFormula' => 'Fórmula', 'bImage' => 'Imagen', 'bFile' => 'Archivo',
    'bVideo' => 'Video', 'bChallenge' => 'Reto', 'bQuiz' => 'Evaluación', 'bTable' => 'Tabla', 'bQuestion' => 'Pregunta', 'bDivider' => 'Separador',
    'nHeading' => 'el título', 'nText' => 'el párrafo', 'nCallout' => 'la nota', 'nFormula' => 'la fórmula', 'nImage' => 'la imagen', 'nFile' => 'el archivo',
    'nDivider' => 'el separador', 'nVideo' => 'el video', 'nChallenge' => 'el reto', 'nQuiz' => 'la evaluación', 'nTable' => 'la tabla', 'nQuestion' => 'la pregunta',
    'nBlock' => 'el bloque',
    'addBelow' => 'Agregar un bloque debajo de {0}', 'moveUp' => 'Subir {0}', 'moveDown' => 'Bajar {0}', 'remove' => 'Eliminar {0}',
    // Marcadores de posición
    'phHeading' => 'Título de la sección', 'phText' => 'Escriba aquí…', 'phCallout' => 'Una idea clave o un consejo',
    'phFormulaCaption' => 'Explicación breve (opcional)', 'phImageCaption' => 'Describa la imagen (también la leen los lectores de pantalla)',
    'phFileCaption' => 'Para qué sirve este archivo (opcional)', 'phVideoCaption' => 'Qué deben observar en el video (opcional)',
    'phTableCaption' => 'Título o explicación de la tabla (opcional)',
    // Imagen y archivo
    'imgUpload' => 'Subir una imagen', 'imgReplace' => 'Cambiar la imagen', 'imgHint' => 'JPG, PNG, WebP o GIF de hasta 8 MB.',
    'fileUpload' => 'Subir un archivo', 'fileReplace' => 'Cambiar el archivo', 'fileHint' => 'PDF, Word, Excel, PowerPoint, OpenDocument, imagen o texto, de hasta 20 MB.',
    'uploading' => 'Subiendo…', 'download' => 'Descargar', 'image' => 'Imagen', 'noFileYet' => 'El docente aún no adjuntó el archivo.',
    'fileChoose' => 'Elija el archivo que quiere subir.', 'imageType' => 'La imagen debe ser JPG, PNG, WebP o GIF.',
    'imageTooBig' => 'La imagen pesa más de {0} MB.', 'fileType' => 'Ese tipo de archivo no se puede subir. Use PDF, Word, Excel, PowerPoint, OpenDocument, imagen o texto.',
    'fileTooBig' => 'El archivo pesa más de {0} MB.', 'storageFull' => 'La institución llegó al límite de almacenamiento de su plan ({0} GB). Pida a la administración ampliar el plan o borrar archivos que ya no use.',
    'fileGone' => 'Ese archivo ya no está disponible.', 'cantEdit' => 'No puede editar este cuaderno.', 'uploadFailed' => 'No se pudo subir el archivo.',
    // Tabla
    'addRow' => 'Agregar fila', 'addCol' => 'Agregar columna', 'delRow' => 'Quitar la fila {0}', 'delCol' => 'Quitar la columna {0}',
    'headerRow' => 'Primera fila como encabezado', 'cell' => 'Fila {0}, columna {1}', 'tableLimit' => 'Hasta {0} filas y {1} columnas.',
    // Pregunta
    'qPh' => 'Escribe la pregunta…', 'qOption' => 'Opción {0}', 'qCorrect' => 'Marcar la opción {0} como la respuesta correcta', 'qAddOption' => 'Agregar opción',
    'qRemoveOption' => 'Quitar la opción {0}', 'qExplanationPh' => 'Explicación que verá el estudiante después de responder (opcional)',
    'qHint' => 'Pregunta de comprobación: el estudiante responde y ve al instante si acertó. No se califica ni se guarda.',
    'qNoCorrect' => 'Marca cuál es la respuesta correcta (el círculo junto a la opción).', 'qQuestion' => 'Pregunta', 'qCheck' => 'Comprueba lo que aprendiste',
    'qRight' => '¡Correcto!', 'qWrong' => 'No es correcta. Inténtalo de nuevo.', 'qTryAgain' => 'Intentar de nuevo', 'qAnswerWas' => 'La respuesta correcta es:',
    // Reto / Evaluación
    'actChoose' => 'Elige {0}', 'actChooseFrom' => 'Elige {0} de esta asignatura…', 'actOne' => 'un reto', 'actOneQuiz' => 'una evaluación',
    'actThe' => 'el reto', 'actTheQuiz' => 'la evaluación', 'actDraft' => 'Borrador: los estudiantes aún no lo ven', 'actPublishedCloses' => 'Publicado · cierra {0}',
    'actPublishedNoDates' => 'Publicado · sin fechas asignadas', 'actStudentsSee' => 'Los estudiantes ven una tarjeta con su estado y el botón para abrirlo, solo cuando ya esté publicado para su grupo.',
    'actNone' => 'Aún no hay retos en esta asignatura. Créalo en {0} y vuelve a elegirlo aquí.', 'actNoneQuiz' => 'Aún no hay evaluaciones en esta asignatura. Créala en {0} y vuelve a elegirla aquí.',
    'activities' => 'Actividades', 'actWillAppear' => 'Aquí aparecerá un reto cuando tu docente lo publique para tu grupo.',
    'actWillAppearQuiz' => 'Aquí aparecerá una evaluación cuando tu docente la publique para tu grupo.', 'closes' => 'Cierra {0}',
    'grade' => 'Nota {0}', 'stSubmitted' => 'Por revisar', 'stReturned' => 'Devuelta: corrígela', 'stInProgress' => 'En curso', 'stLate' => 'Vencida: aún puedes entregar',
    'stMissed' => 'Vencida', 'stTodo' => 'Por hacer', 'ctaSee' => 'Ver {0}', 'ctaContinue' => 'Continuar', 'ctaFix' => 'Corregir', 'ctaQuiz' => 'Presentar la evaluación', 'ctaChallenge' => 'Resolver el reto',
    // Video
    'videoUrl' => 'Enlace del video de YouTube o Vimeo', 'videoPh' => 'Pega el enlace de YouTube o Vimeo',
    'videoBad' => 'Ese enlace no es un video de YouTube ni de Vimeo. Cópialo desde «Compartir» del video.', 'videoPreview' => 'Vista previa del video',
    'videoPaste' => 'Pega arriba el enlace del video (YouTube o Vimeo).', 'videoOf' => 'Video de {0}', 'videoTitled' => 'Video: {0}',
];
