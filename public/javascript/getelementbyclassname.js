function getElementsByClassName(className, tag, parent) {

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
    return result.length ? results : false;

}