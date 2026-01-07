<?php
// Простой просмотр файлов в папке upload
$title = "Файлы в папке upload";
$baseUrl = '/upload/';
$currentDir = dirname(__FILE__);

// Получаем список файлов
$files = [];
if ($handle = opendir($currentDir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != ".." && $entry != "index.php") {
            $filePath = $currentDir . '/' . $entry;
            $files[] = [
                'name' => $entry,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath),
                'is_dir' => is_dir($filePath)
            ];
        }
    }
    closedir($handle);
}

// Сортируем: сначала папки, потом файлы
usort($files, function($a, $b) {
    if ($a['is_dir'] && !$b['is_dir']) return -1;
    if (!$a['is_dir'] && $b['is_dir']) return 1;
    return strcasecmp($a['name'], $b['name']);
});

// Форматируем размер
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #3f8ed8;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .folder {
            color: #3f8ed8;
        }
        .file {
            color: #333;
        }
        a {
            color: #3f8ed8;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .file-size {
            color: #666;
            font-size: 0.9em;
        }
        .file-date {
            color: #666;
            font-size: 0.9em;
        }
        .actions a {
            margin-right: 10px;
            padding: 3px 8px;
            background-color: #3f8ed8;
            color: white;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .actions a:hover {
            background-color: #357abd;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📁 Файлы в папке upload</h1>
        
        <p>Всего файлов: <?= count($files) ?></p>
        
        <?php if (!empty($files)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Имя файла</th>
                        <th>Размер</th>
                        <th>Дата изменения</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td>
                                <?php if ($file['is_dir']): ?>
                                    <span class="folder">📁 <?= htmlspecialchars($file['name']) ?></span>
                                <?php else: ?>
                                    <span class="file">📄 <?= htmlspecialchars($file['name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="file-size">
                                <?= $file['is_dir'] ? 'Папка' : formatSize($file['size']) ?>
                            </td>
                            <td class="file-date">
                                <?= date('d.m.Y H:i:s', $file['modified']) ?>
                            </td>
                            <td class="actions">
                                <?php if (!$file['is_dir']): ?>
                                    <a href="<?= htmlspecialchars($baseUrl . $file['name']) ?>" download>Скачать</a>
                                    <a href="<?= htmlspecialchars($baseUrl . $file['name']) ?>" target="_blank">Открыть</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>В папке нет файлов.</p>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-radius: 4px;">
            <h3>Пути к файлам:</h3>
            <ul>
                <li><strong>Физический путь на сервере:</strong> <?= htmlspecialchars($currentDir) ?></li>
                <li><strong>Веб-путь:</strong> <?= htmlspecialchars($baseUrl) ?></li>
            </ul>
        </div>
    </div>
</body>
</html>