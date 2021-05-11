<?php

class WP_Export_Oxymel extends Oxymel {
	public function optional( $tag_name, $contents ) {
		if ( ( property_exists( $this, $tag_name ) || method_exists( $this, $tag_name ) ) && $contents ) {
			$this->$tag_name( $contents );
		}
		return $this;
	}

	public function optional_cdata( $tag_name, $contents ) {
		if ( property_exists( $this, $tag_name ) && is_object( $this->$tag_name ) && $contents ) {
			$this->$tag_name->contains->cdata( $contents )->end;
		}
		return $this;
	}

	public function cdata( $text ) {
		if ( is_string( $text ) && ! seems_utf8( $text ) ) {
			$text = utf8_encode( $text );
		}
		return parent::cdata( $text );
	}
}

