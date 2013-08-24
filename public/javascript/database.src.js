/**
 *  Returns an array of children elements of a given parent element based on tag and class name.
 *
 *  @param  parent          The parent element
 *  @param  tag             The HTML tags that we are looking for in the parent element
 *  @param  class_name      Optional. Narrows down the search to only the tags having the indicated class
 *  @param  first_only      Optional. Boolean. If set to TRUE, will return only the first child.
 *
 */
function zdc_getElements(parent, tag, className) {

    var

        // initialize the array to be returned
        result = [],

        elements, i,

        // if native support is available
        nativeSupport = parent.getElementsByClassName,

        // the regular expression for matching class names
        regexp = new RegExp('\\b' + className + '\\b', 'i');

    // if parent is undefined, the parent is the document object
    parent || (parent = document);

    // if tag is undefined, match all tags
    tag || (tag = '*');

    // if native implementation is available
    // get elements having the sought class
    if (nativeSupport) elements = parent.getElementsByClassName(className);

    // get children elements matching the given tag
    else elements = parent.getElementsByTagName(tag);

    // the total number of found elements
    i = elements.length;

    // iterate through the found elements
    // decreasing while loop is the fastest way to iterate in JavaScript
    // http://blogs.oracle.com/greimer/entry/best_way_to_code_a
    while (i--)

        // if getElementsByClassName is available natively
        if ((nativeSupport &&

            // and we need specific tags and current element's tag is what we're looking for
            (tag != '*' && elements[i].nodeName.toLowerCase() == tag) ||

            // or we don't need specific tags
            tag == '*'

        // or if getElementsByClassName is not available natively
        ) || (!nativeSupport &&

            // first, test if the class name *contains* what we're searching for
            // because indexOf is much faster than a regular expression
            elements[i].className.indexOf(className) > -1 &&

            // if class name contains what we're searching for
            // use a regular expression to test if there's an exact match
            regexp.test(elements[i].className))

        )

        // add it to results
        result.push(elements[i]);

    // if there are no elements found, return false or, return the found elements if any
    return result.length ? result : false;

}

/**
 *  Sets the "display" css property for an array of elements.
 *
 *  @param  elements    An array of elements to set the "display" css property for
 *  @param  display     The "display" css property ("block" or "none")
 *
 */
function zdc_setDisplay(elements, display)
{

    // iterate through the array of elements
    for (index in elements)

        // set display for each element
        // (exclude the entries in the array that are added through JavaScript)
        if (index.match(/^[0-9]+$/)) elements[index].style.display = display;

}

/**
 *  Closes all tabs shown by the debug console
 *
 *  @param  ignore  Tab to ignore and leave as it is
 *
 */
function zdc_closeAll(skip)
{
    // if tab is not one of the following
    if (!(

        skip.indexOf('zdc-records') > -1 ||
        skip.indexOf('zdc-explain') > -1 ||
        skip.indexOf('zdc-backtrace') > -1

    )) {

        // these are some of the main tabs
        var tabs = ['zdc-errors', 'zdc-successful-queries', 'zdc-unsuccessful-queries', 'zdc-warnings'];

        // iterate through the tabs
        for (index in tabs) {

            var tab = tabs[index];

            // if we should not skip this tab
            if (tab != skip) {

                // get the tab element from the DOM
                var tab = document.getElementById(tab);

                // if tab exists
                if (null !== tab) {

                    // get children <table>s having the 'zdc-entry' class
                    var children = zdc_getElements(tab, 'table', 'zdc-entry');

                    // hide them
                    zdc_setDisplay(children, 'none');

                    // and also hide the tab
                    tab.style.display = 'none';

                }

            }

        }

        // the sub-tabs of the "global" main tab
        var tabs = ['post', 'get', 'session', 'cookie', 'files', 'server'];

        // if the "globals" tab is not to be skipped
        if (null === skip.match(/^zdc\-globals/)) {

            // hide the globals submenu
            document.getElementById('zdc-globals-submenu').style.display = 'none';

            // the parent element
            var parent = document.getElementById('zdc-globals');

            // hide the parent element
            parent.style.display = 'none';

            // iterate through the tabs
            for (index in tabs) {

                // the actual name of the sub-tab element
                var el = 'zdc-globals-' + tabs[index];

                // if element exists, hide it
                if (null !== document.getElementById(el)) document.getElementById(el).style.display = 'none';

            }

        // if a sub-tab of the "globals" main tab is to be skipped
        } else {

            // iterate through the tabs
            for (index in tabs) {

                // the actual name of the sub-tab element
                el = 'zdc-globals-' + tabs[index];

                // if element is not to be skipped and it exists, hide it
                if (el != skip && null !== document.getElementById(el)) document.getElementById(el).style.display = 'none';

            }

        }

    }

}

/**
 *  Toggles the "display" css property of an element or an array of elements.
 *
 *  @param  element     An element or an array of elements.
 *
 */
function zdc_toggle(element)
{

    // close all tabs, except the one given as argument to this function
    zdc_closeAll(element);

    // if element is the actual console
    if (element == 'console') {

        // get the element from the DOM
        var el = document.getElementById('zdc');

        // toggle its display property
        el.style.display = (el.style.display != 'block' ? 'block' : 'none');

    // if not the console element
    } else {

        // let's see what the element is
        switch (element) {

            case 'zdc-errors':
            case 'zdc-successful-queries':
            case 'zdc-unsuccessful-queries':
            case 'zdc-warnings':

                // get the element from the DOM
                var el = document.getElementById(element);

                // if element exists
                if (null != el) {

                    var

                        // get the children <table> elements having the 'zdc-entry' class
                        children = zdc_getElements(el, 'table', 'zdc-entry'),

                        // get the negated value of the display property of the element
                        status = (el.style.display != 'block' ? 'block' : 'none');

                    // update the display property for all the element's children
                    zdc_setDisplay(children, status);

                    // update the display property of the element itself
                    el.style.display = status;
                }

                break;

            case 'zdc-globals-submenu':

                var

                    // get the element from the DOM
                    el = document.getElementById(element),

                    // this is the parent of the element
                    parent = document.getElementById('zdc-globals');

                // toggle display property of the element
                el.style.display = (el.style.display != 'block' ? 'block' : 'none');

                // toggle display property of the parent
                parent.style.display = (parent.style.display != 'block' ? 'block' : 'none');

                break;

            case 'zdc-globals-post':
            case 'zdc-globals-get':
            case 'zdc-globals-session':
            case 'zdc-globals-cookie':
            case 'zdc-globals-files':
            case 'zdc-globals-server':

                // get the element from the DOM
                var el = document.getElementById(element);

                // toggle display property of the element
                el.style.display = (el.style.display != 'block' ? 'block' : 'none');

                break;

            default:

                var

                    // get the element from the DOM
                    el = document.getElementById(element),

                    // se if the element is the "show records", "explain" or "backtrace" tab
                    matches = element.match(/\-([a-z]+)([0-9]+)$/);

                // if the element is the "show records", "explain" or "backtrace" tab
                if (null !== matches) {

                    // when we open the "show records", "explain" or the "backtrace" tab we need to
                    // hide the other two
                    // therefore, get all three tabs
                    var elem1 = document.getElementById('zdc-records-' + matches[1] + matches[2]),
                        elem2 = document.getElementById('zdc-explain-' + matches[1] + matches[2]);
                        elem3 = document.getElementById('zdc-backtrace-' + matches[1] + matches[2]);

                    // if tab exists and is not the one being opened, close it
                    if (null !== elem1 && elem1 != el) elem1.style.display = 'none';
                    if (null !== elem2 && elem2 != el) elem2.style.display = 'none';
                    if (null !== elem3 && elem3 != el) elem3.style.display = 'none';

                }

                // toggle display property of the element
                if (null !== el) el.style.display = (el.style.display != 'block' ? 'block' : 'none');

        }

    }

}

// /* The DOM-ready part is copyright (c) Patrick Hunlock and is taken from http://www.hunlock.com/blogs/Are_you_ready_for_this */

startStack=function() { };  // A stack of functions to run onload/domready

registerOnLoad = function(func) {
    var orgOnLoad = startStack;
    startStack = function () {
        orgOnLoad();
        func();
        return;
    }
}

var ranOnload=false; // Flag to determine if we've ran the starting stack already.

if (document.addEventListener) {

    // Mozilla actually has a DOM READY event.
    document.addEventListener("DOMContentLoaded", function() {
        if (!ranOnload) {
            ranOnload=true;
            startStack();
        }
    }, false);
} else if (document.all && !window.opera) {
    // This is the IE style which exploits a property of the (standards defined) defer attribute
    document.write("<scr" + "ipt id='DOMReady' defer=true " + "src=//:><\/scr" + "ipt>");
    document.getElementById("DOMReady").onreadystatechange = function() {
        if (this.readyState == "complete" && (!ranOnload)) {
            ranOnload=true;
            startStack();
        }
    }
}

var orgOnLoad = window.onload;
window.onload = function() {
    if (typeof(orgOnLoad)=='function') {
        orgOnLoad();
    }
    if (!ranOnload) {
        ranOnload=true;
        startStack();
    }
}

registerOnLoad(function () {

    var

        // are there any error messages?
        errors = document.getElementById('zdc-errors'),

        // are there any unsuccessful queries
        unsuccessful = document.getElementById('zdc-unsuccessful-queries');

    // if there are error messages
    if (null !== errors) {

        // get all the "error messages" tab's children <table>s having the 'zdc-entry' class
        var children = zdc_getElements(errors, 'table', 'zdc-entry');

        // set the found tables' display property to "block"
        zdc_setDisplay(children, 'block');

        // set the "error messages" tab's display property to "block"
        errors.style.display = 'block';

    // if there are unsuccessful queries
    } else if (null !== unsuccessful) {

        // get all the "error messages" tab's children <table>s having the 'zdc-entry' class
        var children = zdc_getElements(unsuccessful, 'table', 'zdc-entry');

        // set the found tables' display property to "block"
        zdc_setDisplay(children, 'block');

        // set the "unsuccessful queries" tab's display property to "block"
        unsuccessful.style.display = 'block';

    } else {

        var

            // if there are successful queries
            successful = document.getElementById('zdc-successful-queries'),

            // are there any queries that need to be highlighted?
            // get all the "successful queries" tab's children <table>s having the 'zdc-highlight' class
            highlight = zdc_getElements(successful, 'table', 'zdc-highlight');

        // set the found tables' display property to "block"
        zdc_setDisplay(highlight, 'block');

        // set the "successful queries" tab's display property to "block"
        successful.style.display = 'block';

    }

});