jQuery(document).ready(function($) {
    console.log('Smart Property JS -- Loaded');
    
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    };
    
    var base_url = location.hostname !== 'localhost' ? location.hostname : 'http://localhost/tgnewton';
    
    // On input of first 3 characters or when Enter is pressed
    $('.property-search-input').keyup(debounce(function(e) {
        var search_term = $(this).val();
        var page = '';
        
        if( e.which == 13 ) {
            console.log('Searched for: ' + search_term);
            $('.property-search-field .search-field-wrap').prepend('<div class="search-keyword">' + search_term + '<i class="fa fa-times"></i></div>');
            $(this).val('');
        }
    }, 1000));
    
    
    // Property Search Submit
    $('.property-search-submit').click(function() {
        var search_terms = $('.property-search-input').val();
        
        // Add keywords
        $('.search-keyword').each(function() {
            search_terms += ' ' + $(this).text();
        });
        
        console.log(search_terms);
        
        var params = {
            'search' : search_terms,
        };
        
        $.ajax({
            method: 'GET',
            url: base_url + '/wp-json/obh-property-search/v1/properties',
            data: params,
            beforeSend: function loader() {
                $('.smart-property-list').text('');
                $('.smart-property-list').append('<div class="loader">Loading...</div>');
            },
            success: function(data, textStatus, jqXHR) {
                $('.smart-property-list .loader').remove();
                console.log(data);
            }
        });
    });
});