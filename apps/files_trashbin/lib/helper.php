<?php

namespace OCA\Files_Trashbin;

class Helper
{
	/**
	 * Retrieves the contents of a trash bin directory.
	 * @param string $dir path to the directory inside the trashbin
	 * or empty to retrieve the root of the trashbin
	 * @return array of files
	 */
	public static function getTrashFiles($dir){
		$result = array();
		$timestamp = null;
		$user = \OCP\User::getUser();

		$view = new \OC_Filesystemview('/' . $user . '/files_trashbin/files');

		if (ltrim($dir, '/') !== '' && !$view->is_dir($dir)) {
			throw new \Exception('Directory does not exists');
		}

		$dirContent = $view->opendir($dir);
		if ($dirContent === false) {
			return $result;
		}
		if (is_resource($dirContent)) {
			while (($entryName = readdir($dirContent)) !== false) {
				if (!\OC\Files\Filesystem::isIgnoredDir($entryName)) {
					$id = $entryName;
					if ($dir === '' || $dir === '/') {
						$pathparts = pathinfo($entryName);
						$timestamp = substr($pathparts['extension'], 1);
						$id = $pathparts['filename'];
					} else if ($timestamp === null) {
						// for subfolders we need to calculate the timestamp only once
						$parts = explode('/', ltrim($dir, '/'));
						$timestamp = substr(pathinfo($parts[0], PATHINFO_EXTENSION), 1);
					}
					$result[] = array(
						'id' => $id,
						'timestamp' => $timestamp,
						'mime' => \OC_Helper::getFileNameMimeType($id),
						'type' => $view->is_dir($dir . '/' . $entryName) ? 'dir' : 'file',
						'location' => $dir,
					);
				}
			}
			closedir($dirContent);
		}

		$files = array();
		$id = 0;
		$dirListing = (rtrim($dir, '/') !== '');
		foreach ($result as $r) {
			$i = array();
			$i['id'] = $id++;
			$i['name'] = $r['id'];
			$i['date'] = \OCP\Util::formatDate($r['timestamp']);
			$i['timestamp'] = $r['timestamp'];
			$i['etag'] = $r['timestamp']; // add fake etag, it is only needed to identify the preview image
			$i['mimetype'] = $r['mime'];
			$i['type'] = $r['type'];
			if ($i['type'] === 'file') {
				$fileinfo = pathinfo($r['id']);
				$i['basename'] = $fileinfo['filename'];
				$i['extension'] = isset($fileinfo['extension']) ? ('.'.$fileinfo['extension']) : '';
			}
			$i['directory'] = $r['location'];
			if ($i['directory'] === '/') {
				$i['directory'] = '';
			}
			$i['permissions'] = \OCP\PERMISSION_READ;
			if (\OCP\App::isEnabled('files_encryption')) {
				$i['isPreviewAvailable'] = false;
			} else {
				$i['isPreviewAvailable'] = \OC::$server->getPreviewManager()->isMimeSupported($r['mime']);
			}
			$i['icon'] = \OCA\Files\Helper::determineIcon($i);
			$files[] = $i;
		}

		usort($files, array('\OCA\Files\Helper', 'fileCmp'));

		return $files;
	}
}
