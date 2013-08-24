<?php
define("Y25",2059219306);//timestamp after 25 years;
#   Copyright (C) 2006-2009 Tobias Leupold <tobias.leupold@web.de>
#
#   This file is part of the b8 package
#
#   This program is free software; you can redistribute it and/or modify it
#   under the terms of the GNU Lesser General Public License as published by
#   the Free Software Foundation in version 2.1 of the License.
#
#   This program is distributed in the hope that it will be useful, but
#   WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
#   or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public
#   License for more details.
#
#   You should have received a copy of the GNU Lesser General Public License
#   along with this program; if not, write to the Free Software Foundation,
#   Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.

# Get the shared functions class file (if not already loaded)


# Use a DBA database (BerkeleyDB)

class b8_storage_memcache extends b8_storage_base
{

	# This is used to reference the DB
	var $_memcache_conn;
    public $config= array(
        "createDb"=>FALSE,
        "host"=>"localhost",
        "port"=>11211,
        "prefix"=>"b8"
    );
	# Constructor
	# Prepares the DB binding and trys to create a new database if requested


    function __construct($config, &$degenerator)
	{

		# Till now, everything's fine
		# Yes, I know that this is crap ;-)
        $this->degenerator = $degenerator;

        foreach($config as $k=>$v){
            $this->config[$k] = $v;
        }

        $mem=new Memcache();
        $mem->addServer($this->config["host"],$this->config["port"]);
        if($mem){
            $this->_memcache_conn=$mem;
            $this->constructed=TRUE;
        }
        else{
            $this->constructed=FALSE;
        }
	}
    function __destruct(){
        $this->_memcache_conn->close();
        unset($this->_memcache_conn);
    }
    /**
     * Does the actual interaction with the database when fetching data.
     *
     * @access protected
     * @param array $tokens
     * @return mixed Returns an array of the returned data in the format array(token => data) or an empty array if there was no data.
     */

    protected function _get_query($tokens)
    {

        $data = array();

        foreach ($tokens as $token) {

            # Try to the raw data in the format "count_ham count_spam lastseen"
            $count = $this->fetch($token);
            if($count !== FALSE) {
                # Split the data by space characters
                $split_data = explode(' ', $count);

                # As the internal variables just have one single value,
                # we have to check for this

                $count_ham  = NULL;
                $count_spam = NULL;

                if(isset($split_data[0]))
                    $count_ham  = (int) $split_data[0];

                if(isset($split_data[1]))
                    $count_spam = (int) $split_data[1];

                # Append the parsed data
                $data[$token] = array(
                    'count_ham'  => $count_ham,
                    'count_spam' => $count_spam
                );
            }
        }

        return $data;

    }
    /**
     * Translates a count array to a count data string
     *
     * @access private
     * @param array ('count_ham' => int, 'count_spam' => int)
     * @return string The translated array
     */

    private function _translate_count($count) {

        # Assemble the count data string
        $count_data = "{$count['count_ham']} {$count['count_spam']}";

        # Remove whitespace from data of the internal variables
        return(rtrim($count_data));

    }

	# Get a token from the database
	function fetch($token)
    {
        if(is_array($token)){
            $bt =debug_backtrace();
            foreacH($bt as $b){
                print $b["file"].":".$b["line"]."<br>";
            }
        }
        return $this->_memcache_conn->get($this->config["prefix"].$token);
	}

    /**
     * Get all data about a list of tags from the database.
     *
     * @access public
     * @param array $tokens
     * @return mixed Returns FALSE on failure, otherwise returns array of returned data in the format array('tokens' => array(token => count), 'degenerates' => array(token => array(degenerate => count))).
     */

    public function get($tokens)
    {

        # First we see what we have in the database.
        $token_data = $this->_get_query($tokens);

        # Check if we have to degenerate some tokens

        $missing_tokens = array();

        foreach($tokens as $token) {
            if(!isset($token_data[$token]))
                $missing_tokens[] = $token;
        }

        if(count($missing_tokens) > 0) {

            # We have to degenerate some tokens
            $degenerates_list = array();

            # Generate a list of degenerated tokens for the missing tokens ...
            $degenerates = $this->degenerator->degenerate($missing_tokens);

            # ... and look them up
            foreach($degenerates as $token => $token_degenerates)
                $degenerates_list = array_merge($degenerates_list, $token_degenerates);

            $token_data = array_merge($token_data, $this->_get_query($degenerates_list));

        }

        # Here, we have all availible data in $token_data.

        $return_data_tokens = array();
        $return_data_degenerates = array();

        foreach($tokens as $token) {

            if(isset($token_data[$token]) === TRUE) {
                # The token was found in the database
                $return_data_tokens[$token] = $token_data[$token];
            }

            else {

                # The token was not found, so we look if we
                # can return data for degenerated tokens

                foreach($this->degenerator->degenerates[$token] as $degenerate) {
                    if(isset($token_data[$degenerate]) === TRUE) {
                        # A degeneration of the token way found in the database
                        $return_data_degenerates[$token][$degenerate] = $token_data[$degenerate];
                    }
                }

            }

        }

        # Now, all token data directly found in the database is in $return_data_tokens
        # and all data for degenerated versions is in $return_data_degenerates, so
        return array(
            'tokens'      => $return_data_tokens,
            'degenerates' => $return_data_degenerates
        );

    }

    /**
     * Store a token to the database.
     *
     * @access protected
     * @param string $token
     * @param string $count
     * @return bool TRUE on success or FALSE on failure
     */

    protected function _put($token, $count) {
        return $this->set($token, $this->_translate_count($count));
    }
    /**
     * Update an existing token.
     *
     * @access protected
     * @param string $token
     * @param string $count
     * @return bool TRUE on success or FALSE on failure
     */

    protected function _update($token, $count)
    {
        return $this->set($token, $this->_translate_count($count));
    }

    /**
     * Remove a token from the database.
     *
     * @access protected
     * @param string $token
     * @return bool TRUE on success or FALSE on failure
     */

    protected function _del($token)
    {
        //return $this->delete($token);
        return $this->_memcache_conn->delete($this->config["prefix"].$token,0);
    }

	# Store a token to the database

	function set($token, $count)
    {
        return $this->_memcache_conn->set($this->config["prefix"].$token,$count,0,Y25);
	}

    /**
     * Does nothing. We just need this function because the (My)SQL backend(s) need it.
     *
     * @access protected
     * @return void
     */

    protected function _commit()
    {
        return;
    }
}

?>
