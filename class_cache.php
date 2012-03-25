<?php

	/**
	 * dG52 PHP Simple Disk Caching class
	 *
	 * @author: Douglas Stridsberg
	 * @email: doggie52@gmail.com
	 * @url: www.douglasstridsberg.com
	 *
	 * Heavily inspired by http://xcache.lighttpd.net/wiki/XcacheApi#aSimpleOOwrapper.
	 *
	 * @todo Implement custom error handling with throw.
	 * Implement expiration time.
	 * Find more efficient way of storing data.
	 *
	 */

	class DiskCache
	{
		/**
		 * Holds the instance of the object.
		 */
		private static $instance;

		/**
		 * The directory in which to cache files, relative to the script itself.
		 */
		public static $cacheDir;

		/**
		 * __construct
		 *
		 * @access private
		 */
		private function __construct()
		{
			// Checks if directory is set
			if ( empty(self::$cacheDir) )
				self::$cacheDir = 'cache/';

			// Trim the path
			self::$cacheDir = trim( self::$cacheDir );

			// Check for trailing slash
			if ( ( strrpos( self::$cacheDir, '/' ) + 1 ) != strlen( self::$cacheDir ) )
				self::$cacheDir = self::$cacheDir . '/';

			// Check for existance of directory, tries to create if non-existant
			if ( !is_dir( self::$cacheDir ) )
				if( !mkdir( self::$cacheDir, 0755 ) )
					exit( 'Directory does not exist and could not be created!' );

			// Checks if directory is writeable
			if ( !is_writeable( self::$cacheDir ) )
				exit( 'Directory is not writeable!' );
		}

		public final function __clone()
		{
			throw new BadMethodCallException("Cloning is not allowed");
		} 

		/**
		* getInstance 
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
		* __set
		*
		* Stores an entry in the caching filesystem and returns whether it was successfully done or not.
		* 
		* @param mixed $name 
		* @param mixed $value 
		* @access public
		* @return bool Whether cache was successfully stored (errors could be if duplicate entry was found).
		*/
		public function __set( $name, $value )
		{
			$name = $this->standardizeName( $name );

			// Checks if the variable is already set
			if ( isset( $this->{$name} ) )
				return false; // return false for now, will be exception later

			// Serialize data
			$value = base64_encode( serialize( $value ) );

			$path = self::$cacheDir . $this->encryptName( $name );

			// Write contents to path
			if ( file_put_contents( $path, $value, LOCK_EX ) !== false )
				return true;
			else
				return false;
		}

		/**
		* __get
		*
		* Fetches an entry in the caching filesystem, returning either the contents or FALSE if there was nothing found.
		* 
		* @param mixed $name 
		* @access public
		* @return $contents Contents of the cached variable, false on failure.
		*/
		public function __get( $name )
		{
			// Checks if the variable is set
			if ( !isset( $this->{$name} ) )
				return false;

			$path = self::$cacheDir . $this->encryptName( $name );

			// Write file contents to variable
			if ( !( $contents = file_get_contents( $path ) ) )
				return false;

			$contents = trim( $contents );
			return unserialize( base64_decode( $contents ) );
		}

		/**
		* __isset 
		* 
		* @param mixed $name 
		* @access public
		* @return bool Whether or not the variable is stored in the cache.
		*/
		public function __isset( $name )
		{
			$path = self::$cacheDir . $this->encryptName( $name );

			if( file_exists( $path ) )
				return true;
			else
				return false;
		}

		/**
		* __unset 
		* 
		* @param mixed $name 
		* @access public
		* @return void
		*/
		public function __unset( $name )
		{
			$path = self::$cacheDir . $this->encryptName( $name );

			unlink( $path );
		}

		/**
		 * encrpytName
		 *
		 * Takes an input and turns it into a hash. Used to name the files in the caching filesystem.
		 *
		 * @param string $name
		 * @access private
		 * @return string $encrypt The hashed $name, false on failure.
		 */
		private function encryptName( $name )
		{
			// Checks whether the input exists or if it's null
			if ( !isset($name) || $name == null )
				return false;

			// Hashes and returns on success
			if ( $encrypt = md5( $name ) )
				return $encrypt;
		}

		/**
		 * standardizeName
		 *
		 * Takes an input and standardizes it, to avoid confusing duplicates.
		 *
		 * @param string $name
		 * @access private
		 * @return string $name The standardized $name.
		 */
		private function standardizeName( $name )
		{
			// Checks whether the input exists or if it's null
			if ( !isset($name) || $name == null )
				return false;

			// Standardizes the name
			$name = trim( $name );
			$name = strtolower( $name );
			$name = urlencode( $name );

			return $name;
		}
	}

?>