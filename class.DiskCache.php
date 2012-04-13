<?php

	/**
	 * dG52 PHP Simple Disk Caching class
	 *
	 * @author: Douglas Stridsberg
	 * @email: doggie52@gmail.com
	 * @url: www.douglasstridsberg.com
	 *
	 * Main class file. Heavily inspired by http://xcache.lighttpd.net/wiki/XcacheApi#aSimpleOOwrapper.
	 *
	 * @todo Find more efficient way of storing data.
	 * @todo Implement cache purging.
	 * @todo Implement individual expiration times for different variables.
	 */

	/**
	 * DiskCache
	 *
	 * @package diskcache
	 */
	class DiskCache
	{
		/**
		 * Holds the instance of the class.
		 *
		 * @var object $instace
		 */
		private static $instance;

		/**
		 * The directory in which to cache files, relative to the script itself.
		 *
		 * @var string $cacheDir
		 */
		public static $cacheDir;

		/**
		 * The default expiration time, in seconds, for cached variables.
		 *
		 * @var int $expirationTime
		 */
		public static $expirationTime;

		/**
		 * The amount of cached variables that have been served.
		 *
		 * @var int $cacheHits
		 */
		public $cacheHits = 0;

		/**
		 * __construct()
		 *
		 * @access private
		 */
		private function __construct()
		{
			// Grabs the CacheException class
			include( 'class.CacheException.php' );
			// Checks if directory is set
			if ( empty( self::$cacheDir ) )
				self::$cacheDir = 'cache/';

			// Trim the path
			self::$cacheDir = trim( self::$cacheDir );

			// Check for trailing slash
			if ( ( strrpos( self::$cacheDir, '/' ) + 1 ) != strlen( self::$cacheDir ) )
				self::$cacheDir = self::$cacheDir . '/';

			// Check for existance of directory, tries to create if non-existant
			if ( !is_dir( self::$cacheDir ) )
				if ( !mkdir( self::$cacheDir, 0755 ) )
					throw new CacheException( "Directory {self::$cacheDir} does not exist and could not be created!" );

			// Checks if directory is writeable
			if ( !is_writeable( self::$cacheDir ) )
				throw new CacheException( "Directory {self::$cacheDir} is not writeable!" );

			// Checks whether an expiration time has been set
			if ( !isset( self::$expirationTime ) )
				self::$expirationTime = 3600;
		}

		/**
		 * __clone()
		 *
		 * @abstract Prevents cloning.
		 *
		 * @access public
		 * @final
		 */
		public final function __clone()
		{
			throw new BadMethodCallException( 'Cloning is not allowed!' );
		}

		/**
		 * __wakeup()
		 *
		 * @abstract Prevents unserializing.
		 *
		 * @access public
		 * @final
		 */
		public final function __wakeup()
		{
			throw new BadMethodCallException( 'Unserializing is not allowed!' );
		}

		/**
		 * getInstance()
		 *
		 * @abstract Enfores the Singleton pattern, returns the one and only instance of itself, stored in itself.
		 *
		 * @static
		 * @access public
		 * @return object DiskCache instance.
		 */
		public static function getInstance()
		{
			// If it doesn't already exist, create itself
			if ( !( self::$instance instanceof DiskCache ) )
				self::$instance = new DiskCache;

			return self::$instance;
		}

		/**
		 * store()
		 *
		 * @abstract Stores an entry in the caching filesystem and returns whether it was successfully done or not.
		 *
		 * @param mixed $name
		 * @param mixed $value
		 * @access public
		 * @return bool Whether cache was successfully stored (errors could be if duplicate entry was found).
		 */
		public function store( $name, $value )
		{
			$name = $this->standardizeName( $name );

			// Checks if the variable is already set
			if ( $this->checkSet( $name ) ) {
				// Checks if the variable has expired
				if ( $this->hasExpired( $name ) )
					$this->delete( $name );
				else
					return false;
			}

			// Serialize data
			$value = base64_encode( serialize( $value ) );

			$path = $this->path( $name );

			// Write contents to path
			if ( file_put_contents( $path, $value, LOCK_EX ) !== false )
				return true;
			else
				throw new CacheException( 'Cache contents could not be stored!' );
		}

		/**
		 * __set()
		 *
		 *
		 * @param mixed $name
		 * @param mixed $value
		 * @access public
		 */
		public function __set( $name, $value )
		{
			return $this->store( $name, $value );
		}

		/**
		 * get()
		 *
		 * @abstract Fetches an entry in the caching filesystem, returning either the contents or FALSE if there was nothing found.
		 *
		 * @param mixed $name
		 * @access public
		 * @return $contents Contents of the cached variable, false on failure.
		 */
		public function get( $name )
		{
			$name = $this->standardizeName( $name );

			// Checks if the variable is set
			if ( !$this->checkSet( $name ) )
				return false;

			// Checks if the variable has expired
			if ( $this->hasExpired( $name ) ) {
				$this->delete( $name );
				return false;
			}

			$path = $this->path( $name );

			// Write file contents to variable
			if ( ( $contents = file_get_contents( $path ) ) === false )
				throw new CacheException( 'Variable could not be retrieved from file!' );

			// Increment hits-counter
			$this->cacheHits++;

			$contents = trim( $contents );
			return unserialize( base64_decode( $contents ) );
		}

		/**
		 * __get()
		 *
		 *
		 * @param mixed $name
		 * @access public
		 */
		public function __get( $name )
		{
			return $this->get( $name );
		}

		/**
		 * prune()
		 *
		 * @abstract Prunes the existing cache files, looking for old enough cache to delete.
		 *
		 * @todo Allow the amount of files to check per prune to be set by the user.
		 *
		 * @return int $count The number of files that were pruned.
		 */
		public function prune()
		{
			// Initiate filename array
			if ( ( $filenames = scandir( self::$cacheDir ) ) === false )
				throw new CacheException( 'Cache directory could not be scanned for pruning!' );

			$count = 0;

			// Main pruning loop
			foreach ( $filenames as $filename )
			{
				// The two first $filenames are current and parent directory, let's ignore those
				if ( $filename[0] == '.' )
					continue;

				$path = self::$cacheDir . $filename;
				if ( !( $cachestat = stat( $path ) ) )
					throw new CacheException( "Last modification time for file \"{$filename}\" could not be parsed!" );

				// If current time is between last modification time and last modification time plus expiration time, it has not expired
				if (
						$cachestat['mtime'] <= time()
						&&
						time() < ( $cachestat['mtime'] + self::$expirationTime )
				)
					continue;
				else
				{
					if ( !unlink( $path ) )
						throw new CacheException( 'Cache file could not be deleted!' );
					else
						$count++;
				}
			}

			return $count;
		}

		/**
		 * checkSet()
		 *
		 * @param mixed $name
		 * @access public
		 * @return bool Whether or not the variable is stored in the cache.
		 */
		public function checkSet( $name )
		{
			// Checks if the variable has expired
			if ( $this->hasExpired( $name ) ) {
				$this->delete( $name );
				return false;
			}

			$name = $this->standardizeName( $name );

			$path = $this->path( $name );

			if ( file_exists( $path ) )
				return true;
			else
				return false;
		}

		/**
		 * __isset()
		 *
		 * @param mixed $name
		 * @access public
		 */
		public function __isset( $name )
		{
			return $this->checkSet( $name );
		}

		/**
		 * delete()
		 *
		 * @param mixed $name
		 * @access public
		 * @return void
		 */
		public function delete( $name )
		{
			$name = $this->standardizeName( $name );

			// Checks if cache entry exists
			if ( !$this->checkSet( $name ) )
				return false;

			$path = $this->path( $name );

			if ( !unlink( $path ) )
				throw new CacheException( 'Cached variable could not be deleted!' );
		}

		/**
		 * __unset()
		 *
		 * @param mixed $name
		 * @access public
		 */
		public function __unset( $name )
		{
			return $this->delete( $name );
		}

		/**
		 * hasExpired()
		 *
		 * @abstract Checks whether a certain cached variable or file has expired or not.
		 *
		 * @param string $name
		 * @access private
		 * @return bool Whether it has expired or not.
		 */
		private function hasExpired( $name )
		{
			$name = $this->standardizeName( $name );

			$path = $this->path( $name );

			// Checks if cache entry exists
			// Let's not use the checkSet here, but we need to figure out a better method
			if ( !file_exists( $name ) )
				return false;

			if ( !( $cachestat = stat( $path ) ) )
				throw new CacheException( "Last modification time for \"{$name}\" could not be parsed!" );

			// If current time is between last modification time and last modification time plus expiration time, it has not expired
			if (
				$cachestat['mtime'] <= time()
				&&
				time() < ( $cachestat['mtime'] + self::$expirationTime )
			)
				return false;
			else
				return true;
		}


		/**
		 * encrpytName()
		 *
		 * @abstract Takes an input and turns it into a hash. Used to name the files in the caching filesystem.
		 *
		 * @param string $name
		 * @access private
		 * @return string $encrypt The hashed $name.
		 */
		private function encryptName( $name )
		{
			// Hashes and returns on success
			$encrypt = dechex( crc32( $name ) );
			return $encrypt;
		}

		/**
		 * standardizeName()
		 *
		 * @abstract Takes an input and standardizes it, to avoid confusing duplicates.
		 * Note that yes, this function is sometimes run twice on strings -- this is not a problem as it will then just return the same string.
		 *
		 * @param string $name
		 * @access private
		 * @return string $name The standardized $name.
		 */
		private function standardizeName( $name )
		{
			// Standardizes the name
			$name = trim( $name );
			$name = strtolower( $name );

			return $name;
		}

		/**
		 * path()
		 *
		 * @abstract Takes an input and returns the path to the cached variable with that $name.
		 * If $type is NAME_FILE, the name is not encrypted again.
		 *
		 * @param string $name
		 * @access private
		 * @return string $path The path to the cached variable.
		 */
		private function path( $name )
		{
			$path = self::$cacheDir . $this->encryptName( $name );

			return $path;
		}
	}

?>