<?php 

################################################################################################
#  Macro callbacks ( user functions )

///////////// IELegacy /////////////

// Fix opacity in ie6/7/8
if ( !function_exists( 'csscrush_Opacity' ) ) {
	function csscrush_Opacity ( $prop, $val ) {
		$msval = round( $val*100 );
		$out = "-ms-filter: \"progid:DXImageTransform.Microsoft.Alpha(Opacity={$msval})\";
				filter: progid:DXImageTransform.Microsoft.Alpha(Opacity={$msval});
				zoom:1;
				{$prop}: {$val}";
		return preg_replace( "#\s+#", ' ', $out );
	}
}
// Fix display:inline-block in ie6/7
if ( !function_exists( 'csscrush_Display' ) ) {
	function csscrush_Display ( $prop, $val ) {
		if ( $val == 'inline-block' ) {
			return "{$prop}:{$val};*{$prop}:inline;*zoom:1";
		}
		return "{$prop}:{$val}";
	}
}
// Fix min-height in ie6
if ( !function_exists( 'csscrush_Min_Height' ) ) {
	function csscrush_Min_Height ( $prop, $val ) {return "{$prop}:{$val};_height:{$val}";}
}

///////////// CSS3 /////////////

if ( !function_exists( 'csscrush_Border_Radius' ) ) {
	function csscrush_Border_Radius ( $prop, $val ) {
		return "-moz-{$prop}:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Border_Top_Left_Radius' ) ) {
	function csscrush_Border_Top_Left_Radius ( $prop, $val ) {
		return "-moz-border-radius-topleft:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Border_Top_Right_Radius' ) ) {
	function csscrush_Border_Top_Right_Radius ( $prop, $val ) {
		return "-moz-border-radius-topright:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Border_Bottom_Right_Radius' ) ) {
	function csscrush_Border_Bottom_Right_Radius ( $prop, $val ) {
		return "-moz-border-radius-bottomright:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Border_Bottom_Left_Radius' ) ) {
	function csscrush_Border_Bottom_Left_Radius ( $prop, $val ) {
		return "-moz-border-radius-bottomleft:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Box_Shadow' ) ) {
	function csscrush_Box_Shadow ( $prop, $val ) {
		return "-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Transform' ) ) {
	function csscrush_Transform ( $prop, $val ) {
		return "-o-{$prop}:{$val};-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Transition' ) ) {
	function csscrush_Transition ( $prop, $val ) {
		return "-o-{$prop}:{$val};-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Background_Size' ) ) {
	function csscrush_Background_Size ( $prop, $val ) {
		return "-o-{$prop}:{$val};-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Box_Sizing' ) ) {
	function csscrush_Box_Sizing ( $prop, $val ) {
		return "-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
	}
}
if ( !function_exists( 'csscrush_Background_Image' ) ) {
	function csscrush_Background_Image ( $prop, $val ) {
		if ( strpos( $val, 'linear-gradient' ) !== false ) {
			$val = substr( $val, strpos( $val, '(' ) + 1 );
			$args = preg_split( '#\s*,\s*#', str_replace( ')', '', $val ) );
			$args = array_map( 'trim', $args );

			// top, #444444, #999999
			foreach ( $args as &$arg ) {
				$re = '!^#([a-z0-9])([a-z0-9])([a-z0-9])$!i';
				if ( preg_match( $re, $arg ) ) {
					$arg = preg_replace( $re, '#$1$1$2$2$3$3', $arg );
				}
			}
			list( $dir, $col1, $col2 ) = $args;
			// Dropped support for IE since the IE filter spoils text rendering
			$out = "
				background-color:{$col1};
				background-image: -webkit-gradient(
					linear, left top, left bottom, color-stop( 0, {$col1} ), color-stop( 1, {$col2} ));
				background-image:-moz-linear-gradient(top, {$col1}, {$col2});
				background-image:linear-gradient(top, {$col1}, {$col2});";
			return preg_replace( "#\s+#", ' ', $out );
		}
		return false;
	}
}
