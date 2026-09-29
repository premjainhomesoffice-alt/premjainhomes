(function() {
    var ticking = false;
    $(window).on('scroll', function() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(function() {
            // Only toggle .fixed-top here (solid background once scrolled).
            // The show/hide-on-scroll-direction animation lower in this file
            // already handles sliding the header in and out via `top`, using
            // a CSS transition. Also adding the animate.css "slideInDown"
            // transform animation here fought with that transition on the
            // same element and made the header appear to slide the wrong way.
            var scrollValue = $(window).scrollTop();
            if (scrollValue > 70) {
                $('.header_menu').addClass('fixed-top');
            } else {
                $('.header_menu').removeClass('fixed-top');
            }
            ticking = false;
        });
    });
})();


  
"use strict";


/*======== Doucument Ready Function =========*/
jQuery(document).ready(function () {

      // slicknav
    /**
     * Slicknav - a Mobile Menu
     */
    var $slicknav_label;
    $('.responsive-menu').slicknav({
      duration: 500,
      easingOpen: 'easeInExpo',
      easingClose: 'easeOutExpo',
      closedSymbol: '<i class="fa fa-plus"></i>',
      openedSymbol: '<i class="fa fa-minus"></i>',
      prependTo: '#slicknav-mobile',
      allowParentLinks: true,
      label:"" 
    });

    var $slicknav_label;
    $('#responsive-menu').slicknav({
      duration: 0,
      closedSymbol: '<i class="fa fa-plus"></i>',
      openedSymbol: '<i class="fa fa-minus"></i>',
      prependTo: '#slicknav-mobile',
      allowParentLinks: true,
      label:""
    });

    // Slide-in drawer for the mobile nav, instead of the default dropdown
    // "popup" under the header. Duration is 0 above so slicknav shows/hides
    // instantly (just toggling its .slicknav_hidden class); the actual
    // sliding motion is done in CSS via a transform transition on
    // .slicknav_nav, keyed off that same class.
    var $mobileNavOverlay = $('<div class="mobile-nav-overlay"></div>').appendTo('body');

    // The header (.main_header_area) has its own z-index:999 stacking
    // context, and .slicknav_nav was nested inside it, so its z-index:2000
    // was only ever compared against siblings *inside* that context — the
    // body-level overlay (z-index 1999) still composited above the whole
    // header stack and visually greyed out the drawer. Moving the drawer
    // panel to be a direct child of <body>, alongside the overlay, fixes
    // the stacking so the drawer's own z-index is finally compared at the
    // right level and renders above the overlay as solid white.
    $('.slicknav_nav').appendTo('body');

    function closeMobileNav() {
      $('.slicknav_btn.slicknav_open').trigger('click');
    }

    $(document).on('click', '.slicknav_btn', function() {
      setTimeout(function() {
        $mobileNavOverlay.toggleClass('show', $('.slicknav_btn').hasClass('slicknav_open'));
      }, 0);
    });

    $mobileNavOverlay.on('click', closeMobileNav);

    if (!$('.slicknav_nav .slicknav_close-btn').length) {
      $('.slicknav_nav').prepend('<div class="slicknav_close-btn"><i class="fa fa-times"></i></div>');
    }
    if (!$('.slicknav_nav .slicknav_drawer-logo').length) {
      $('.slicknav_nav').prepend('<div class="slicknav_drawer-logo"><img src="images/logo.png" alt="Prem Jain Homes"></div>');
    }
    $(document).on('click', '.slicknav_close-btn', closeMobileNav);


    /**
     * Sticky Header
     */
        
    var stickyTicking = false;
    $(window).scroll(function(){
      if (stickyTicking) return;
      stickyTicking = true;
      window.requestAnimationFrame(function() {
        if ($(window).scrollTop() > 10) {
          $('.navbar').addClass('navbar-sticky-in')
        } else {
          $('.navbar').removeClass('navbar-sticky-in')
        }
        stickyTicking = false;
      });
    })
    
    /**
     * Main Menu Slide Down Effect
     */
     
    var selected = $('#navbar li');
    // Mouse-enter dropdown
    selected.on("mouseenter", function() {
        $(this).find('ul').first().stop(true, true).delay(350).slideDown(500, 'easeInOutQuad');
    });

    // Mouse-leave dropdown
    selected.on("mouseleave", function() {
        $(this).find('ul').first().stop(true, true).delay(100).slideUp(150, 'easeInOutQuad');
    });

    /**
     *  Arrow for Menu has sub-menu
     */
    if ($(window).width() > 992) {
      $(".navbar-arrow ul ul > li").has("ul").children("a").append("<i class='arrow-indicator fa fa-angle-right'></i>");
    }

});


 (function(){

    var doc = document.documentElement;
    var w   = window;

    /*
    define four variables: curScroll, prevScroll, curDirection, prevDirection
    */

    var curScroll;
    var prevScroll = w.scrollY || doc.scrollTop;
    var curDirection = 0;
    var prevDirection = 0;

    /*
    how it works:
    -------------
    create a scroll event listener
    create function to check scroll position on each scroll event,
    compare curScroll and prevScroll values to find the scroll direction
    scroll up - 1, scroll down - 2, initial - 0
    then set the direction value to curDirection
    compare curDirection and prevDirection
    if it is different, call a function to show or hide the header
    example:
    step 1: user scrolls down: curDirection 2, prevDirection 0 > hide header
    step 2: user scrolls down again: curDirection 2, prevDirection 2 > already hidden, do nothing
    step 3: user scrolls up: curDirection 1, prevDirection 2 > show header
    */

    var header = document.getElementById('header_menu');
    var toggled;
    var threshold = 200;

    var checkScroll = function() {
        curScroll = w.scrollY || doc.scrollTop;
        if(curScroll > prevScroll) {
            // scrolled down
            curDirection = 2;
        }
        else {
            //scrolled up
            curDirection = 1;
        }

        if(curDirection !== prevDirection) {
            toggled = toggleHeader();
        }

        prevScroll = curScroll;
        if(toggled) {
            prevDirection = curDirection;
        }
    };

    var toggleHeader = function() { 
        toggled = true;
        if(curDirection === 2 && curScroll > threshold) {
            header.classList.add('hide');
            jQuery('.sticky1').addClass('tab-sticky');
        }
        else if (curDirection === 1) {
            header.classList.remove('hide');
            jQuery('.sticky1').removeClass('tab-sticky');
        }
        else {
            toggled = false;
        }
        return toggled;
    };

    var directionTicking = false;
    window.addEventListener('scroll', function() {
        if (directionTicking) return;
        directionTicking = true;
        window.requestAnimationFrame(function() {
            checkScroll();
            directionTicking = false;
        });
    }, { passive: true });

})();



