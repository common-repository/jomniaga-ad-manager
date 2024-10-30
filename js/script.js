jQuery(document).ready(function() { 
    jQuery('#jomniagaform').submit(function(){ 
        var wpjomniaga_username = jQuery('#wpjomniaga_username').val();
        var wpjomniaga_tracking_code = jQuery('#wpjomniaga_tracking_code').val();
        var wpjomniaga_convert_limit_perpage = jQuery('#wpjomniaga_convert_limit_perpage').val();
        var wpjomniaga_keyword_limit_perpage = jQuery('#wpjomniaga_keyword_limit_perpage').val();
        var wpjomniaga_convert_limit_percomment = jQuery('#wpjomniaga_convert_limit_percomment').val();
        var wpjomniaga_keyword_limit_percomment = jQuery('#wpjomniaga_keyword_limit_percomment').val();
    
        result = true;
        jQuery('.error').remove();
        jQuery('.form-invalid').removeClass('form-invalid');
        if(hasWhiteSpace(wpjomniaga_username)){        
            jQuery('#wpjomniaga_username').parent().parent().addClass('form-invalid');
            jQuery('#wpjomniaga_username').after('<span class="error" style="color:red;">Username can\'t contain spaces</span>');
            result =  false;
        }
        if(hasWhiteSpace(wpjomniaga_tracking_code)){        
            jQuery('#wpjomniaga_tracking_code').parent().parent().addClass('form-invalid');
            jQuery('#wpjomniaga_tracking_code').after('<span class="error" style="color:red;">Tracking code can\'t contain spaces</span>');
            result =  false;
        }
    
        if(is_int(wpjomniaga_username)){        
            jQuery('#wpjomniaga_username').parent().parent().addClass('form-invalid');
            jQuery('#wpjomniaga_username').after('<span class="error" style="color:red;">Username can\'t be a number</span>');
            result =  false;
        }
    
    
        if(!is_int(wpjomniaga_convert_limit_perpage) || !is_int(wpjomniaga_keyword_limit_perpage)){
            jQuery('#wpjomniaga_keyword_limit_perpage').parent().parent().addClass('form-invalid');
            jQuery('#wpjomniaga_keyword_limit_perpage').parent().append('<span class="error" style="color:red;">You must enter an integer number</span>');
            result =  false;
        }
    
    
        if(!is_int(wpjomniaga_keyword_limit_percomment) || !is_int(wpjomniaga_convert_limit_percomment)){
            jQuery('#wpjomniaga_keyword_limit_percomment').parent().parent().addClass('form-invalid');
            jQuery('#wpjomniaga_keyword_limit_percomment').parent().append('<span class="error" style="color:red;">You must enter an integer number</span>');
            result =  false;
        }
        
        if(!result){
            return false;
        }
            
        
    } );
});

function hasWhiteSpace(s) {
    return /\s/g.test(s);

}

function is_int(num){
    var intRegex = /^\d+$/;
    if(intRegex.test(num)) {
        return true;
    }else{
        return false;
    }
}
