var base_url=window.location.origin;var pathname=window.location.pathname;var host=window.location.host;var pathArray=window.location.pathname.split('/').filter(Boolean);var urlParams=new URLSearchParams(window.location.search);function qp_has(key){return urlParams.has(key)}
function qp_get(key){return urlParams.get(key)}
function qp_add_hidden(key,value){return urlParams.append(key,value)}
function qp_add(key,value){const searchParams=new URLSearchParams(window.location.search);searchParams.set(key,value);const newUrl=`${window.location.pathname}?${searchParams.toString()}`;history.pushState(null,'',newUrl)}
function get_path_array(index=1){return pathArray[index]}
function ucfirst(str){return str[0].toUpperCase()+str.slice(1)}