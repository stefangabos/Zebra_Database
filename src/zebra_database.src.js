/* global _$ */

// prevent multiple initializations
if (typeof _$ === 'undefined') {

    // custom build of zebrajs (see https://github.com/stefangabos/zebrajs)
    /* eslint-disable */
    /* jshint ignore:start */
    !function(){"use strict";var s={},e=0,u=function(t,e,n){var o,r=[];if("string"==typeof t&&"body"===t.toLocaleLowerCase()&&(t=document.body),"string"==typeof t)if(0===t.indexOf("<")&&1<t.indexOf(">")&&2<t.length)(e=document.createElement("div")).innerHTML=t,r.push(e.firstChild);else if(e?"object"==typeof e&&e.version?e=e[0]:"string"==typeof e&&(e=document.querySelector(e)):e=document,t.match(/^#[^s]+$/))r.push(e.querySelector(t));else if(n)try{r.push(e.querySelector(t))}catch(t){}else try{r=Array.prototype.slice.call(e.querySelectorAll(t))}catch(t){}else if("object"==typeof t&&(t instanceof Document||t instanceof Window||t instanceof Element||t instanceof Text))r.push(t);else if(t instanceof NodeList)r=Array.prototype.slice.call(t);else if(Array.isArray(t))r=r.concat(t);else if("object"==typeof t&&t.version)return t;for(o in r=r.filter(function(t){return null!=t}),u.fn)r[o]=u.fn[o];return r};u.fn={version:"1.0.2"},u.fn.addClass=function(t){return this._class("add",t)},u.fn.attr=function(n,e){if("object"==typeof n)this.forEach(function(t){for(var e in n)t.setAttribute(e,n[e])});else if("string"==typeof n){if(void 0===e)return this[0].getAttribute(n);this.forEach(function(t){!1===e||null===e?t.removeAttribute(n):t.setAttribute(n,e)})}return this},u.fn.closest=function(e){var n=[];return this[0].matches(e)?this:(this.forEach(function(t){for(;!((t=t.parentNode)instanceof Document);)if(t.matches(e)){-1===n.indexOf(t)&&n.push(t);break}}),u(n))},u.fn.each=function(t){for(var e=0;e<this.length;e++)if(!1===t.call(this[e],e,this[e]))return},u.fn.hasClass=function(t){for(var e=0;e<this.length;e++)if(this[e].classList.contains(t))return!0;return!1},u.fn.is=function(e){var n=!1;return this.forEach(function(t){if("string"==typeof e&&t.matches(e)||"object"==typeof e&&e.version&&t===e[0]||"object"==typeof e&&(e instanceof Document||e instanceof Element||e instanceof Text||e instanceof Window)&&t===e)return!(n=!0)}),n},u.fn.not=function(n){return u(this.filter(function(e,t){return"function"==typeof n&&void 0!==n.call?n.call(e,t):Array.isArray(n)?!n.filter(function(t){return u(e).is(t)}).length:!u(e).is(n)}))},u.fn.on=function(n,o,r,i){var e,a,c,t;if("object"!=typeof n)return e=n.split(" "),"function"==typeof o&&("boolean"==typeof r&&(i=r),r=o),this.forEach(function(t){e.forEach(function(e){c=!1,a=e.split("."),n=a[0],a=a[1]||"",void 0===s[n]&&(s[n]=[]),"string"==typeof o?(c=function(t){i&&u(this).off(e,r),this!==t.target&&t.target.matches(o)&&r(t)},t.addEventListener(n,c)):i?(c=function(t){u(this).off(e,r),r(t)},t.addEventListener(n,c)):t.addEventListener(n,r),s[n].push([t,r,a,c])})}),this;for(t in n)this.on(t,n[t])},u.fn.ready=function(t){return"complete"===document.readyState||"loading"!==document.readyState?t():document.addEventListener("DOMContentLoaded",t),this},u.fn.removeClass=function(t){return this._class("remove",t)},u.fn.trigger=function(n,o){return this.forEach(function(t){var e=document.createEvent("HTMLEvents");e.initEvent(n,!0,!0),"object"==typeof o&&Object.keys(o).forEach(function(t){e[t]=o[t]}),t.dispatchEvent(e)}),this},u.fn._class=function(n,t){return t=t.split(" "),this.forEach(function(e){t.forEach(function(t){e.classList["add"===n||"toggle"===n&&!e.classList.contains(t)?"add":"remove"](t)})}),this},Element.prototype.matches||(Element.prototype.matches=Element.prototype.matchesSelector||Element.prototype.mozMatchesSelector||Element.prototype.msMatchesSelector||Element.prototype.oMatchesSelector||Element.prototype.webkitMatchesSelector||function(t){for(var e=(this.document||this.ownerDocument).querySelectorAll(t),n=e.length;0<=--n&&e.item(n)!==this;);return-1<n}),window._$=window.jQuery=u}();
    /* eslint-enable */
    /* jshint ignore:end */

    _$(document).ready(function() {

        'use strict';

        // use $ instead of _$
        (function($) {

            // closes everything
            $('.zdc-close').on('click', function() {
                $('#zdc .zdc-visible').not('#zdc-main').removeClass('zdc-visible');
            });

            $('ul#zdc-main > li a').on('click', function() {
                window.scrollTo({ left: 0, top: 0 });
            });

            // handle visibility toggling
            $('.zdc-toggle').each(function() {

                $(this).on('click', function() {
                    var $element = $(this),
                        target, $target, is_open;

                    // the section that needs to be toggled
                    target = $element.attr('class').replace(/^.*?zdc\-toggle/, '').replace(/\zdc\-toggle\-id/, '').trim();

                    // the element to be toggled
                    $target = $('#' + target);

                    // if we have to open a section by ID
                    if ($element.hasClass('zdc-toggle-id') || !$target.length) {

                        // if we need to toggle the visibility of a data table, lock on the data table
                        if (!$target.length) $target = $('div.' + target, $element.closest('.zdc-data'));

                        // is the section open?
                        // (we have to do this before hiding all the sections)
                        is_open = $target.hasClass('zdc-visible');

                        // if we are viewing a global section, keep the submenu open
                        if ($element.attr('class').match(/zdc\-globals\-/) && !$element.attr('class').match(/zdc\-globals\-submenu/))

                            $('#zdc .zdc-visible').not('#zdc-globals-submenu').not('#zdc-main').removeClass('zdc-visible');

                        // if we are *not* toggling the visibility of a data table, hide any open section
                        else if (!$target.hasClass('zdc-data-table')) $('#zdc .zdc-visible').not('#zdc-main').removeClass('zdc-visible');

                        // if we are toggling a data table, hide any open data table
                        else $('#zdc .zdc-data-table.zdc-visible').not('#zdc-main').removeClass('zdc-visible');

                    // if we have to open a section by class name
                    } else {

                        // is the section open?
                        // (we have to do this before hiding all the sections)
                        is_open = $('.zdc-entry.zdc-visible', $target).length;

                        // hide open section
                        $('#zdc .zdc-visible').not('#zdc-main').removeClass('zdc-visible');

                        // the element(s) for which we are going to toggle visibility
                        $target = $('.zdc-entry', $target);

                    }

                    // if section is open, close it
                    if (is_open) $target.removeClass('zdc-visible');

                    // if section is closed, open it
                    else $target.addClass('zdc-visible');

                });

            });

        })(_$);

    });

}
