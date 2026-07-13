//Assuming URL is "https://gvelondon.com/cars/bugatti-veyron-sang-noir/"
var base_url = window.location.origin; //https://gvelondon.com
var pathname = window.location.pathname; //cars/bugatti-veyron-sang-noir/
var host = window.location.host; //gvelondon.com
var pathArray = window.location.pathname.split('/').filter(Boolean); //returns object ['', 'cars', ''] 
var urlParams = new URLSearchParams(window.location.search);


function qp_has(key) {
    return urlParams.has(key);
}

function qp_get(key) {
    return urlParams.get(key);
}

function qp_add_hidden(key, value) {
    return urlParams.append(key, value);
}

function qp_add(key, value) {
    // 1. Create URLSearchParams object from current query string
    const searchParams = new URLSearchParams(window.location.search);

    // 2. Set a new key-value pair (or update an existing one)
    searchParams.set( key, value );

    // 3. Construct the new URL
    const newUrl = `${window.location.pathname}?${searchParams.toString()}`;

    // 4. Update the browser's history
    history.pushState(null, '', newUrl);
}

function get_path_array(index = 1) {
    return pathArray[index];
}

/*converts the first character of a string to uppercase while leaving the rest of the string untouched*/
function ucfirst(str) {
  return str[0].toUpperCase() + str.slice(1);
}