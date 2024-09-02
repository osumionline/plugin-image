<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Plugins;

use \GdImage;
use \Exception;
use Osumi\OsumiFramework\Tools\OTools;

/**
 * Utility class with tools to manipulate images (create new, resize, get information...)
 */
class OImage {
	private ?string  $filename   = null;
	private ?GdImage $image      = null;
	private ?int     $image_type = null;
	private array    $error_messages = [];

	function __construct() {
		$this->error_messages = [
			'FILE_NOT_FOUND'  => OTools::getMessage('PLUGIN_IMAGE_FILE_NOT_FOUND'),
			'LOAD_ERROR'      => OTools::getMessage('PLUGIN_IMAGE_LOAD_ERROR'),
			'FILE_NOT_LOADED' => OTools::getMessage('PLUGIN_IMAGE_FILE_NOT_LOADED')
		];
	}

	/**
	 * Get Base64 encoded image file's extension
	 *
	 * @param string $data Base64 encoded image file
	 *
	 * @return string Image extension
	 */
	public static function getImageExtension(string $data): string {
		$arr_data = explode(';', $data);
		$arr_data = explode(':', $arr_data[0]);
		$arr_data = explode('/', $arr_data[1]);

		return $arr_data[1];
	}

	/**
	 * Save a Base64 encoded image file on given location
	 *
	 * @param string $path Path where the file should be saved
	 *
	 * @param string $base64_string Base64 encoded image file
	 *
	 * @param string $name Name of the resulting image file
	 *
	 * @param string $ext Extension of the resulting image file
	 *
	 * @param bool $overwrite Overwrite if image found on given location (default true)
	 *
	 * @return string Full path of the resulting image file
	 */
	public static function saveImage(string $path, string $base64_string, string $name, string $ext, bool $overwrite=true): string {
		$full_path = $path.$name.'.'.$ext;

		if (file_exists($full_path)) {
			unlink($full_path);
		}

		$ifp = fopen($full_path, "wb");
		$data = explode(',', $base64_string);
		fwrite($ifp, base64_decode($data[1]));
		fclose($ifp);

		return $full_path;
	}

	/**
	 * Get loaded files image type
	 *
	 * @return int Image type constant of the loaded file (or null if file hasn't been loaded yet)
	 */
	public function getImageType(): ?int {
		return $this->image_type;
	}

	/**
	 * Load into memory the specified file
	 *
	 * @param string $filename Path of the file to be loaded
	 *
	 * @return void
	 */
	public function load(string $filename): void {
		try {
			if (!file_exists($filename)) {
				throw new Exception(sprintf($this->error_messages['FILE_NOT_FOUND'], $filename), 100);
			}
			$this->filename   = $filename;
			$image_info       = getimagesize($filename);
			$this->image_type = $image_info[2];

			switch ($this->image_type) {
				case IMAGETYPE_JPEG: { $this->image = imagecreatefromjpeg($filename); }
				break;
				case IMAGETYPE_GIF: { $this->image = imagecreatefromgif($filename);  }
				break;
				case IMAGETYPE_PNG: { $this->image = imagecreatefrompng($filename);  }
				break;
				case IMAGETYPE_WEBP: { $this->image = imagecreatefromwebp($filename); }
				break;
			}

			if ($this->image_type === IMAGETYPE_PNG || $this->image_type === IMAGETYPE_WEBP) {
				imagepalettetotruecolor($this->image);
				imagealphablending($this->image, true);
				imagesavealpha($this->image, true);
			}
		}
		catch(Exception $e) {
			$this->filename   = null;
			$this->image      = null;
			$this->image_type = null;
			if ($e->getCode() == 100) {
				throw new Exception($e->getMessage());
			}
			else {
				throw new Exception($this->error_messages['LOAD_ERROR']);
			}
		}
	}

	/**
	 * Save previously loaded file into the specified format, with a given compression rato and new file permissions
	 *
	 * @param string $filename Path of the new file to be created
	 *
	 * @param int $image_type New images file format
	 *
	 * @param int $compression Compression rate of the new file
	 *
	 * @param int $permissions Permissions of the new file
	 *
	 * @return void
	 */
	public function save(string $filename, int $image_type=IMAGETYPE_JPEG, int $compression=75, int $permissions=null): void {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		switch ($image_type) {
			case IMAGETYPE_JPEG: { imagejpeg($this->image, $filename, $compression); }
			break;
			case IMAGETYPE_GIF: { imagegif($this->image,  $filename); }
			break;
			case IMAGETYPE_PNG: { imagepng($this->image,  $filename); }
			break;
			case IMAGETYPE_WEBP: { imagewebp($this->image, $filename); }
			break;
		}
		if (!is_null($permissions)) {
			chmod($filename, $permissions);
		}
	}

	/**
	 * Change format of the loaded file
	 *
	 * @param int $image_type Format to be converted to
	 *
	 * @return void
	 */
	public function output(int $image_type=IMAGETYPE_JPEG): void {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		switch ($image_type) {
			case IMAGETYPE_JPEG: { imagejpeg($this->image); }
			break;
			case IMAGETYPE_GIF: {  imagegif($this->image);  }
			break;
			case IMAGETYPE_PNG: {  imagepng($this->image);  }
			break;
			case IMAGETYPE_WEBP: { imagewebp($this->image); }
			break;
		}
	}

	/**
	 * Get width of the loaded file
	 *
	 * @return int Width of the loaded file
	 */
	public function getWidth(): int {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		return imagesx($this->image);
	}

	/**
	 * Get height of the loaded file
	 *
	 * @return int Height of the loaded file
	 */
	public function getHeight(): int {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		return imagesy($this->image);
	}

	/**
	 * Resize loaded file to a fixed height mantaining the ratio
	 *
	 * @param int $height Height of the new file
	 *
	 * @return void
	 */
	public function resizeToHeight(int $height): void {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		$ratio = $height / $this->getHeight();
		$width = $this->getWidth() * $ratio;
		$this->resize($width, $height);
	}

	/**
	 * Resize loaded file to a fixed width mantaining the ratio
	 *
	 * @param int $width Width of the new file
	 *
	 * @return void
	 */
	public function resizeToWidth(int $width): void {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		$ratio  = $width / $this->getWidth();
		$height = intval($this->getheight() * $ratio);
		$this->resize($width, $height);
	}

	/**
	 * Scale loaded file to a given percentage ratio
	 *
	 * @param int $scale Scale ratio to be resized to
	 *
	 * @return void
	 */
	public function scale(int $scale): void {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		$width  = $this->getWidth() * $scale/100;
		$height = $this->getheight() * $scale/100;
		$this->resize($width, $height);
	}

	/**
	 * Resize image to a fixed width/height
	 *
	 * @param int $width New width of the loaded file
	 *
	 * @param int $height New height of the loaded file
	 *
	 * @return void
	 */
	public function resize(int $width, int $height): void {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		$new_image = imagecreatetruecolor($width, $height);
		if ($this->image_type === IMAGETYPE_PNG || $this->image_type === IMAGETYPE_WEBP) {
			imagealphablending($new_image, false);
			imagesavealpha($new_image, true);
			$transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
			imagefilledrectangle($new_image, 0, 0, $width, $height, $transparent);
		}
		imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
		$this->image = $new_image;
	}

	/**
	 * Rotate image with given degrees. Doesn't work on GIF files
	 *
	 * @param int $degrees Number of degrees of rotation to be applied to the loaded image
	 *
	 * @return void
	 */
	public function rotate(int $degrees): void {
		if (is_null($this->image)) {
			throw new Exception($this->error_messages['FILE_NOT_LOADED']);
		}
		if ($this->image_type === IMAGETYPE_WEBP) {
			$source = imagecreatefromwebp($this->filename);
			imagealphablending($source, false);
			imagesavealpha($source, true);

			$rotation = imagerotate($source, $degrees, imageColorAllocateAlpha($source, 0, 0, 0, 127));
			imagealphablending($rotation, false);
			imagesavealpha($rotation, true);

			$this->image = $rotation;
		}
		if ($this->image_type === IMAGETYPE_PNG) {
			$source = imagecreatefrompng($this->filename);
			imagealphablending($source, false);
			imagesavealpha($source, true);

			$rotation = imagerotate($source, $degrees, imageColorAllocateAlpha($source, 0, 0, 0, 127));
			imagealphablending($rotation, false);
			imagesavealpha($rotation, true);

			$this->image = $rotation;
		}
		if ($this->image_type === IMAGETYPE_JPEG) {
			$source = imagecreatefromjpeg($this->filename);
			$rotation = imagerotate($source, $degrees, 0);
			$this->image = $rotation;
		}
	}
}
