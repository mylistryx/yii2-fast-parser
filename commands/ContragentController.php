<?php

namespace app\commands;

use app\components\parsers\XmlParser;
use app\models\od\Source;
use Yii;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use ZipArchive;

final class ContragentController extends Controller
{
    private bool $parseXmlFiles = false;
    private bool $checkParsedDirectories = false;

    /**
     * Строим структуру начиная от корня
     * @param bool $parseXmlFiles
     * @param bool $checkParsedDirectories
     * @return int
     * @throws ErrorException
     * @throws Exception
     */
    public function actionIndex(
        bool $parseXmlFiles = false,
        bool $checkParsedDirectories = false,
    ): int
    {
        $this->parseXmlFiles = $parseXmlFiles;
        $this->checkParsedDirectories = $checkParsedDirectories;
        foreach ($this->dirList(Yii::getAlias('@sources')) as $root) {
            if (!Source::findOne(['parent_id' => null, 'path' => $root])) {
                (new Source(['path' => $root, 'mime' => Source::TYPE_DIR]))->makeRoot()->save();
            }
        }

        foreach (Source::find()->roots()->all() as $sourceRoot) {
            $this->checkPath($sourceRoot);
        }

        return ExitCode::OK;
    }

    /**
     * @throws ErrorException
     * @throws Exception
     */
    public function actionZip(bool $onlyNew = true): int
    {
        ContragentController::actionUnlock(3);
        $this->parseXmlFiles = true;
        $query = Source::find()->zip()->orderBy(['parsed_at' => SORT_ASC]);

        if ($onlyNew) {
            $query->notParsed();
            $query->notStarted();
            $query->addOrderBy(['created_at' => SORT_ASC]);
            $query->andWhere(['started_at' => null]);
        }

        while ($source = $query->one()) {
            $source->started_at = date('Y-m-d H:i:s');
            $source->save(false);
            $this->stdout("\nUnparsed ZIP archives left: " . Source::find()->zip()->notParsed()->count());
            $this->processZipFile($source->parent, $source->path);
            $this->actionUnlock();
        }

        return ExitCode::OK;
    }

    /**
     * Вычистить директории забытые
     * @return int
     * @throws ErrorException
     */
    public function actionClean(): int
    {
        foreach ($items = Source::find()->zip()->alreadyParsed()->all() as $item) {
            $dir = self::tempDir($item->path);
            $this->stdout("$dir");
            if (is_dir($dir)) {
                FileHelper::removeDirectory($dir);
                $this->stdout(" [ DELETED]\n");
            } else {
                $this->stdout(" [ CLEAN ]\n");
            }
        }
        return ExitCode::OK;
    }

    public function actionUnlock(int $hours = 6): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-$hours hours"));
        $count = Source::updateAll(['started_at' => null], [
            'and',
            ['<', 'started_at', $date],
            ['parsed_at' => null],
        ],
        );
        $this->stdout("\nItems unlocked: $count\n");
        return ExitCode::OK;
    }

    public function actionUnsort(): int
    {
        $sources = Yii::getAlias('@sources');
        $dir = $sources . DIRECTORY_SEPARATOR . 'EGRUL_406';
        $unsortDir = $dir . DIRECTORY_SEPARATOR . '!UNSORT';
        $items = $this->dirList($unsortDir);
        foreach ($items as $item) {
            $item2 = explode('_', $item);
            $name = explode('-', $item2[1]);
            echo "\n" . $dirname = $dir . DIRECTORY_SEPARATOR . $name[2] . '.' . $name[1] . '.' . $name[0];
            FileHelper::createDirectory($dirname);
            copy($unsortDir . DIRECTORY_SEPARATOR . $item, $dirname . DIRECTORY_SEPARATOR . $item);
            FileHelper::unlink($unsortDir . DIRECTORY_SEPARATOR . $item);
        }

        return ExitCode::OK;
    }


    /**
     * @param Source $source
     * @param bool $temporaryFiles
     * @return void
     * @throws ErrorException
     * @throws Exception
     */
    private function checkPath(Source $source, bool $temporaryFiles = false): void
    {
        $tempPath = $temporaryFiles ? self::tempDir($source->path) : $source->fullPath;
        $this->stdout("\nCheck path: $tempPath");
        $subDirs = $this->dirList($tempPath);

        foreach ($subDirs as $subDir) {
            $fullPath = $tempPath . DIRECTORY_SEPARATOR . $subDir;
            if (is_dir($fullPath)) {
                $this->processDir($source, $subDir, $temporaryFiles);
            } elseif (is_file($fullPath)) {
                $this->processFile($source, $subDir, $temporaryFiles);
            }
        }
    }

    /**
     * Обработать рекурсивно директории по полному пути
     * @param Source $parentSource
     * @param string $chunkPath
     * @param bool $temporary
     * @return void
     */
    private function processDir(Source $parentSource, string $chunkPath, bool $temporary = false): void
    {
        $this->stdout("\nProcessing DIR: {$chunkPath}");
        $source = Source::factory($parentSource->tree_id, $parentSource->id, $chunkPath);
        if ($source->isNewRecord) {
            $source->mime = Source::TYPE_DIR;
            $source->temporary = $temporary;
            $source->appendTo($parentSource)->save();
        }

        if (empty($source->parsed_at) or $this->checkParsedDirectories) {
            $this->checkPath($source, $temporary);
            $source->parsed_at = date('Y-m-d H:i:s');
            $source->save(false);
        }
    }

    /**
     * Обработать файл по полному пути
     * @param Source $parentSource
     * @param string $chunkPath
     * @param bool $temporaryFiles
     * @return void
     * @throws ErrorException
     * @throws Exception
     */
    private function processFile(Source $parentSource, string $chunkPath, bool $temporaryFiles = false): void
    {
        if ($temporaryFiles) {
            $fullPath = self::tempDir($parentSource->path) . DIRECTORY_SEPARATOR . $chunkPath;
        } else {
            $fullPath = $parentSource->fullPath . DIRECTORY_SEPARATOR . $chunkPath;
        }


        switch (mime_content_type($fullPath)) {
            case 'application/zip':
                $this->processZipFile($parentSource, $chunkPath, $temporaryFiles);
                return;
            case 'application/json':
                $this->processJsonFile($parentSource, $chunkPath, $temporaryFiles);
                return;
            case 'text/xml':
                $this->processXmlFile($parentSource, $chunkPath, $temporaryFiles);
                return;
            case 'text/csv':
                $this->processCsvFile($parentSource, $chunkPath, $temporaryFiles);
                return;
            default:
                $this->stderr("\nUnhandled file mime type");
        }
    }

    /**
     * Получить список файлов из ZIP архива в источники
     * @param Source $parentSource
     * @param string $chunkPath
     * @return void
     * @throws Exception
     * @throws ErrorException
     */
    private function processZipFile(Source $parentSource, string $chunkPath): void
    {
        $fullPath = $parentSource->fullPath . DIRECTORY_SEPARATOR . $chunkPath;
        $size = round(filesize($fullPath) / 1024);
        $this->stdout("\nProcessing ZIP: $fullPath [ $size Kb ]", Console::FG_GREEN);
        $zip = new ZipArchive();
        if (false === $zip->open($fullPath)) {
            $this->stderr(" [ SKIP DAMAGED ] ");
            return;
        }

        $source = Source::factory($parentSource->tree_id, $parentSource->id, $chunkPath);
        if ($source->isNewRecord) {
            $source->mime = mime_content_type($fullPath);
            $source->appendTo($parentSource)->save();
        }

        $crc = sha1_file($fullPath);

        $fakePath = self::tempDir($source->path);

        if (($source->parsed_at) && ($source->crc == $crc)) {
            $this->stdout("\nSKIP already parsed ZIP file");
            $source->started_at = null;
            $source->save(false);
            FileHelper::removeDirectory($fakePath);
            return;
        }
        /**
         * Обрабатываем XML либо просто набиваем список
         */
        if ($this->parseXmlFiles) {
            $source->started_at = date('Y-m-d H:i:s');
            $source->save(false);
            FileHelper::createDirectory($fakePath);
            $zip->extractTo($fakePath);
            $zip->close();
            $this->checkPath($source, true);
            FileHelper::removeDirectory($fakePath);
            $source->crc = $crc;
            $source->parsed_at = date("Y-m-d H:i:s");
            $source->started_at = null;
            $source->save();
        } else {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $import_file = basename($stat['name']);

                $innerSource = Source::factory($source->tree_id, $source->id, $import_file);
                if ($innerSource->isNewRecord) {
                    $innerSource->temporary = true;
                    $innerSource->appendTo($source)->save();
                }

            }
            $zip->close();
            $source->crc = $crc;
            $source->save(false);
        }

    }

    private function processCsvFile(Source $source, string $chunkPath): void
    {
        $this->stdout("\n Processing {$source->fullPath}");
        $this->stdout("\n Csv Source: {$source->parent->fullPath}");

        $this->stdout(" [ DONE ]", Console::FG_GREEN);
    }

    private function processJsonFile(Source $source, string $chunkPath): void
    {
        $this->stdout("\n Processing {$source->fullPath}");
        $this->stdout("\n Json Source: {$source->parent->fullPath}");

        $this->stdout(" [ DONE ]", Console::FG_GREEN);
    }

    private function processXmlFile(Source $parentSource, string $chunkPath, bool $temporaryFiles = false): void
    {
        $time = date('H:i:s');

        if ($parentSource->isArchive()) {
            $fullPath = self::tempDir($parentSource->path) . DIRECTORY_SEPARATOR . $chunkPath;
            $size = round(filesize($fullPath) / 1024);
            $this->stdout("\nProcessing XML from ZIP: $fullPath [ $size Kb started at $time ]");
        } else {
            $fullPath = $parentSource->fullPath . DIRECTORY_SEPARATOR . $chunkPath;
            $size = round(filesize($fullPath) / 1024);
            $this->stdout("\nProcessing XML: $fullPath [ $size Kb started at $time ]");
        }

        if (!file_exists($fullPath)) {
            $this->stdout(" [ ERROR ]", Console::FG_RED);
            $this->stdout("\nFile not found: $fullPath", Console::FG_GREY);
            return;
        }

        $source = Source::factory($parentSource->tree_id, $parentSource->id, $chunkPath);
        $source->mime = mime_content_type($fullPath);
        if ($source->isNewRecord) {
            $source->temporary = $temporaryFiles;
            $source->appendTo($parentSource)->save();
        }

        if (!$this->parseXmlFiles) {
            return;
        }

        $crc = sha1_file($fullPath);

        if ($crc == $source->crc) {
            $this->stdout(" [ SKIP ]", Console::FG_YELLOW);
            return;
        }

        if ($source->started_at) {
            $this->stdout(" [ ALREADY IN PROGRESS ]", Console::FG_BLUE);
            return;
        }

        $source->started_at = date('Y-m-d H:i:s');
        $source->save(false);
        XmlParser::factory($source, $fullPath);
        $source->crc = $crc;
        $source->parsed_at = date('Y-m-d H:i:s');
        $source->started_at = null;
        $source->save();

        $this->stdout(" [ DONE ]", Console::FG_GREEN);
    }

    private function dirList(string $path): array
    {
        return array_diff(scandir($path), ['..', '.', '!UNSORT']);
    }

    private static function tempDir(string $name): string
    {
        return Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . md5($name);
    }
}