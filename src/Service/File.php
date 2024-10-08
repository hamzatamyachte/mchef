<?php

namespace App\Service;

use App\Exceptions\ExecFailed;
use App\Traits\ExecTrait;
use splitbrain\phpcli\Exception;

class File extends AbstractService {
    use ExecTrait;

    final public static function instance(): File {
        return self::setup_instance();
    }

    /**
     * Copy files from point a to b.
     * @param $src
     * @param $target
     * @return string - Output from exec
     * @throws ExecFailed
     */
    public function copyFiles($src, $target): string {
        $cmd = "cp -r $src/{.,}* $target";
        return $this->exec($cmd, "Failed to copy files from $src to $target: {{output}}");
    }

    private function folderRestrictionCheck(string $path, string $action) {
        if (!is_dir($path)) {
            throw new Exception('Invalid path '.$path);
        }
        if (realpath($path) === '/') {
            throw new Exception('You cannot '.$action.' files from root!');
        }
        if (realpath($path) === '/etc') {
            throw new Exception('You cannot '.$action.' files from etc folder!');
        }
        if (realpath($path) === '/bin') {
            throw new Exception('You cannot '.$action.' files from bin folder!');
        }
        if (realpath($path) === '/usr/bin') {
            throw new Exception('You cannot '.$action.' files from /usr/bin folder!');
        }
    }

    public function cmdFindAllFilesExcluding(array $files, array $paths): string {
        $files = array_map(function($file) { return ' -not -file "'.$file.'"';}, $files );
        $paths = array_map(function($path) { return ' -not -path "'.$path.'" -not -path "'.$path.'/*"';}, $paths );
        $not = implode(' ', $files).implode(' ', $paths);
        $cmd = "find . $not";
        return $cmd;
    }

    /**
     * Delete all files
     * @param string $path - target path of which to delete files from
     * @param array $files
     * @param array $paths
     * @return string
     * @throws ExecFailed
     */
    public function deleteAllFilesExcluding(string $path, array $files, array $paths): string {
        $this->folderRestrictionCheck($path, 'delete');
        $cmd = $this->cmdFindAllFilesExcluding($files, $paths);
        $cmd = "$cmd -delete";
        return $this->exec($cmd);
    }

    public function deleteDir($path) {
        if (empty($path)) {
            return false;
        }
        return is_file($path) ?
            @unlink($path) :
            array_map(function($path) {
                $this->deleteDir($path);
            }, glob($path.'/*')) == @rmdir($path);
    }

    public function tempDir() {
        $tempDir = sys_get_temp_dir().'/'.uniqid(sha1(microtime()), true);
        mkdir($tempDir);
        return $tempDir;
    }

    function findFileInOrAboveDir($filename, ?string $dir = null): ?string {
        $currentDir = $dir ?? getcwd();
        $rootDir = DIRECTORY_SEPARATOR === '\\' ? getRootDirectoryWindows($currentDir) : DIRECTORY_SEPARATOR;

        while ($currentDir !== $rootDir) {
            $filePath = $currentDir . DIRECTORY_SEPARATOR . $filename;

            if (file_exists($filePath)) {
                return $filePath;
            }

            $currentDir = realpath($currentDir . DIRECTORY_SEPARATOR . '..');
        }

        return null; // File not found.
    }

    function getRootDirectoryWindows($currentDir) {
        $rootDir = '';

        if (preg_match('/^[A-Z]:\\\\/', $currentDir, $matches)) {
            $rootDir = $matches[0];
        }

        return $rootDir;
    }
}
