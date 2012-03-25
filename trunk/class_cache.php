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
	 * Find more efficient way of storing data.
	 * Implement cache purging.
	 *
	 */

	class DiskCache
	{
		/**
		 * Holds the instance of the class.
		 */
		private static $instance;

		/**
		 * The directory in which to cache files, relative to the script itself.
		 */
		public static $cacheDir;

		/**
		 * The default expiration time, in seconds, for cached variables.
		 */
		public static $expirationTime;

		/**
		 * __construct
		 *
		 * @access private
		 */
		private function __construct()
		{
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
				if( !mkdir( self::$cacheDir, 0755 ) )
					exit( 'Directory does not exist and could not be created!' );

			// Checks if directory is writeable
			if ( !is_writeable( self::$cacheDir ) )
				exit( 'Directory is not writeable!' );

			// Checks whether an expiration time has been set
			if ( !isset( self::$expirationTime ) )
				self::$expirationTime = 3600;
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
		* store
		*
		* Stores an entry in the caching filesystem and returns whether it was successfully done or not.
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
			if ( $this->checkSet( $name ) )
			{
				// Checks if the variable has expired
				if ( $this->hasExpired( $name ) )
				{
					$this->delete( $name );
				}
				else
					return false;
			}

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
		* __set
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
		* get
		*
		* Fetches an entry in the caching filesystem, returning either the contents or FALSE if there was nothing found.
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
			if ( $this->hasExpired( $name ) )
			{
				$this->delete( $name );
				return false;
			}

			$path = self::$cacheDir . $this->encryptName( $name );

			// Write file contents to variable
			if ( !( $contents = file_get_contents( $path ) ) )
				return false;

			$contents = trim( $contents );
			return unserialize( base64_decode( $contents ) );
		}

		/**
		* __get
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
		* checkSet
		* 
		* @param mixed $name 
		* @access public
		* @return bool Whether or not the variable is stored in the cache.
		*/
		public function checkSet( $name )
		{
			$name = $this->standardizeName( $name );

			$path = self::$cacheDir . $this->encryptName( $name );

			if( file_exists( $path ) )
				return true;
			else
				return false;
		}

		/**
		* __isset 
		* 
		* @param mixed $name 
		* @access public
		*/
		public function __isset( $name )
		{
			return $this->checkSet( $name );
		}

		/**
		* delete
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

			$path = self::$cacheDir . $this->encryptName( $name );

			unlink( $path );
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
			return $this->delete( $name );
		}

		/**
		 * hasExpired
		 *
		 * Checks whether a certain cached variable has expired or not.
		 *
		 * @param string $name
		 * @access private
		 * @return bool Whether it has expired or not.
		 */
		private function hasExpired( $name )
		{
			$name = $this->standardizeName( $name );

			// Checks if cache entry exists
			if ( !$this->checkSet( $name ) )
				return false;

			$path = self::$cacheDir . $this->encryptName( $name );

			if ( !( $cachestat = stat( $path ) ) )
				exit( 'Last modification time for "'.$name.'" could not be parsed!' );

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
			// Hashes and returns on success
			if ( $encrypt = md5( $name ) )
				return $encrypt;
			else
				return false;
		}

		/**
		 * standardizeName
		 *
		 * Takes an input and standardizes it, to avoid confusing duplicates.
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
			$name = urlencode( $name );

			return $name;
		}
	}

?>