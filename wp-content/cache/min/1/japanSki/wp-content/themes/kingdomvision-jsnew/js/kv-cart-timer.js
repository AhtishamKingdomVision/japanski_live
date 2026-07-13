(function(){'use strict';const STORAGE_KEY='rb_cart';const TIMER_META_KEY='rb_cart_timer';const DEFAULTS={roomboss_seconds:10*60,bedbank_seconds:6*60*60,};if(typeof window.kvCartTimer==='object'&&window.kvCartTimer){if(typeof window.kvCartTimer.roomboss_seconds==='number'){DEFAULTS.roomboss_seconds=window.kvCartTimer.roomboss_seconds}
if(typeof window.kvCartTimer.bedbank_seconds==='number'){DEFAULTS.bedbank_seconds=window.kvCartTimer.bedbank_seconds}}
const TIMER_SELECTOR='.rb-cart-timer';const PROCEED_SELECTORS=['.rb-proceed-btn','.rb-pay-btn','.rb-book-now-btn'];const ROOMBOSS_BTN_SELECTORS=['.rb-proceed-btn','.rb-pay-btn'];const BEDBANK_BTN_SELECTORS=['.rb-proceed-btn','.rb-book-now-btn'];const ROOMBOSS_ONLY_BTN_SELECTORS=['.rb-pay-btn'];const BEDBANK_ONLY_BTN_SELECTORS=['.rb-book-now-btn'];const REFRESH_BTN_CLASS='rb-cart-refresh-btn';const EXPIRED_MSG_CLASS='rb-cart-expired-msg';function readCart(){try{const raw=localStorage.getItem(STORAGE_KEY);if(!raw)return{items:[]};const parsed=JSON.parse(raw);if(parsed&&Array.isArray(parsed.items))return parsed;if(Array.isArray(parsed))return{items:parsed};return{items:[]}}catch(e){return{items:[]}}}
function writeCart(cart){try{localStorage.setItem(STORAGE_KEY,JSON.stringify(cart))}catch(e){}}
function readTimerMeta(){try{const raw=localStorage.getItem(TIMER_META_KEY);if(!raw)return null;const parsed=JSON.parse(raw);return(parsed&&typeof parsed==='object')?parsed:null}catch(e){return null}}
function writeTimerMeta(meta){try{localStorage.setItem(TIMER_META_KEY,JSON.stringify(meta))}catch(e){}}
function clearTimerMeta(){try{localStorage.removeItem(TIMER_META_KEY)}catch(e){}}
function isBedbankItem(item){if(!item)return!1;const v=item.is_bedbank??item.isBedBank;if(typeof v==='string'){return v==='1'||v.toLowerCase()==='true'}
return!!v}
function isRoombossItem(item){if(!item)return!1;return!isBedbankItem(item)}
function getPrimaryItem(cart){if(!cart||!Array.isArray(cart.items)||!cart.items.length)return null;return cart.items[0]}
function getDurationSeconds(propertyType){if(propertyType==='bedbank')return DEFAULTS.bedbank_seconds;return DEFAULTS.roomboss_seconds}
function fetchDurationForProperty(propertyId,fallbackSeconds){return new Promise((resolve)=>{if(!propertyId||typeof kv_object==='undefined'||!kv_object.ajaxurl){resolve(fallbackSeconds);return}
try{const body=new URLSearchParams({action:'kv_get_cart_timer_config',property_id:String(propertyId),});fetch(kv_object.ajaxurl+'?v='+new Date().getTime(),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,}).then((r)=>r.json()).then((res)=>{if(res&&res.success&&res.data&&typeof res.data.duration==='number'){resolve(res.data.duration)}else{resolve(fallbackSeconds)}}).catch(()=>resolve(fallbackSeconds))}catch(e){resolve(fallbackSeconds)}})}
async function startTimer(cart){const item=getPrimaryItem(cart);if(!item){clearTimerMeta();return}
const propertyId=item.hotel_type_id||item.property_id||item.hotelId||'';const isBedbank=isBedbankItem(item);const propertyType=isBedbank?'bedbank':'roomboss';const fallbackSeconds=getDurationSeconds(propertyType);const durationSeconds=await fetchDurationForProperty(propertyId,fallbackSeconds);const meta={property_id:propertyId,property_type:propertyType,duration:durationSeconds,duration_ms:durationSeconds*1000,expires_at:Date.now()+(durationSeconds*1000),is_bedbank:isBedbank,is_roomboss:!isBedbank,};writeTimerMeta(meta)}
function isExpired(meta){if(!meta||!meta.expires_at)return!0;return Date.now()>=meta.expires_at}
function remainingMs(meta){if(!meta||!meta.expires_at)return 0;return Math.max(0,meta.expires_at-Date.now())}
function formatRemaining(ms){if(ms<=0)return'00:00';const totalSeconds=Math.floor(ms/1000);const hours=Math.floor(totalSeconds/3600);const minutes=Math.floor((totalSeconds%3600)/60);const seconds=totalSeconds%60;const pad=(n)=>String(n).padStart(2,'0');if(hours>0){return `${hours}:${pad(minutes)}:${pad(seconds)}`}
return `${pad(minutes)}:${pad(seconds)}`}
function ensureTimerNode(cart){const existingAll=document.querySelectorAll(TIMER_SELECTOR);if(existingAll.length>1){for(let i=1;i<existingAll.length;i++){existingAll[i].remove()}}
let $node=document.querySelector(TIMER_SELECTOR);if($node){if(!$node.parentElement||!findCartContainer()){const inserted=insertTimerNode($node);if(!inserted){return null}}
return $node}
const wrapper=document.createElement('div');wrapper.className='rb-cart-timer';wrapper.setAttribute('role','status');wrapper.setAttribute('aria-live','polite');wrapper.innerHTML=`
            <div class="rb-cart-timer__icon" aria-hidden="true">
                <i class="fa-regular fa-clock"></i>
            </div>
            <div class="rb-cart-timer__text">
                <span class="rb-cart-timer__label">Time remaining</span>
                <span class="rb-cart-timer__countdown" data-role="countdown">--:--</span>
            </div>
            <div class="rb-cart-timer__refresh" data-role="refresh-wrap" style="display:none;">
                <button type="button" class="rb-cart-refresh-btn" data-role="refresh">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    Refresh Availability
                </button>
            </div>
        `;const inserted=insertTimerNode(wrapper);if(!inserted){return null}
return wrapper}
function insertTimerNode(node){const container=findCartContainer();if(container){container.parentNode.insertBefore(node,container);return!0}
return!1}
function findCartContainer(){const selectors=['#rbCartFooter','.rb-cart-footer','#rbCartBody','.rb-cart-body','.rb-cart-wrap','aside.rb-cart','.rb-summary-box','#rb-booking-details-widget',];for(let i=0;i<selectors.length;i++){const el=document.querySelector(selectors[i]);if(el&&el.parentElement){return el}}
return null}
function waitForCartContainer(timeoutMs=10000){return new Promise((resolve,reject)=>{const existing=findCartContainer();if(existing){resolve(existing);return}
let timeoutId=null;const observer=new MutationObserver(()=>{const el=findCartContainer();if(el){observer.disconnect();if(timeoutId)clearTimeout(timeoutId);resolve(el)}});observer.observe(document.documentElement,{childList:!0,subtree:!0,});timeoutId=setTimeout(()=>{observer.disconnect();reject(new Error('cart_container_not_found'))},timeoutMs)})}
function getProceedButtons(){const all=[];PROCEED_SELECTORS.forEach((sel)=>{document.querySelectorAll(sel).forEach((el)=>all.push(el))});const seen=new Set();return all.filter((el)=>{if(!el||seen.has(el))return!1;seen.add(el);return!0})}
function collectBySelectors(selectors){const all=[];selectors.forEach((sel)=>{document.querySelectorAll(sel).forEach((el)=>all.push(el))});const seen=new Set();return all.filter((el)=>{if(!el||seen.has(el))return!1;seen.add(el);return!0})}
function getRoomBossButtons(){return collectBySelectors(ROOMBOSS_BTN_SELECTORS)}
function getBedBankButtons(){return collectBySelectors(BEDBANK_BTN_SELECTORS)}
function getRoomBossOnlyButtons(){return collectBySelectors(ROOMBOSS_ONLY_BTN_SELECTORS)}
function getBedBankOnlyButtons(){return collectBySelectors(BEDBANK_ONLY_BTN_SELECTORS)}
function resolveBookingMode(){try{const metaRaw=localStorage.getItem(TIMER_META_KEY);if(metaRaw){const meta=JSON.parse(metaRaw);if(meta&&meta.is_bedbank===!0)return'bedbank';if(meta&&meta.is_roomboss===!0)return'roomboss';if(meta&&meta.property_type==='bedbank')return'bedbank'}}catch(e){}
try{const cartRaw=localStorage.getItem(STORAGE_KEY);if(cartRaw){const cart=JSON.parse(cartRaw);if(cart&&Array.isArray(cart.items)&&cart.items.length){const primary=cart.items[0];if(primary&&isBedbankItem(primary))return'bedbank'}}}catch(e){}
return'unknown'}
function setButtonVisible(btn,visible){if(!btn)return;if(visible){btn.removeAttribute('data-rb-timer-hidden');btn.style.removeProperty('display');btn.disabled=!1}else{btn.setAttribute('data-rb-timer-hidden','1');btn.style.display='none';btn.disabled=!0}}
function setProceedVisible(visible,mode){if(!visible){getProceedButtons().forEach((btn)=>setButtonVisible(btn,!1));return}
const effectiveMode=mode||resolveBookingMode();if(effectiveMode==='bedbank'){getBedBankButtons().forEach((btn)=>setButtonVisible(btn,!0));getRoomBossOnlyButtons().forEach((btn)=>setButtonVisible(btn,!1))}else if(effectiveMode==='roomboss'){getRoomBossButtons().forEach((btn)=>setButtonVisible(btn,!0));getBedBankOnlyButtons().forEach((btn)=>setButtonVisible(btn,!1))}else{getRoomBossButtons().forEach((btn)=>setButtonVisible(btn,!0));getBedBankOnlyButtons().forEach((btn)=>setButtonVisible(btn,!1))}}
function showRefresh(show){const node=document.querySelector(TIMER_SELECTOR);if(!node)return;const wrap=node.querySelector('[data-role="refresh-wrap"]');if(wrap){wrap.style.display=show?'':'none'}}
function showExpiredMessage(message){if(!message)message='Your reservation period has expired.';document.querySelectorAll('.'+EXPIRED_MSG_CLASS).forEach((el)=>el.remove());const node=document.createElement('div');node.className=EXPIRED_MSG_CLASS;node.setAttribute('role','alert');node.innerHTML=`<i class="fa-solid fa-circle-exclamation"></i><span>${message}</span>`;const target=document.querySelector('.rb-booking-wrapper, .rb-cart, #rb-booking-details-widget, body');if(target){target.prepend(node)}}
function handleRoombossExpiration(cart){setProceedVisible(!1);showRefresh(!0);const node=document.querySelector(TIMER_SELECTOR);if(node)node.classList.add('is-expired');document.dispatchEvent(new CustomEvent('rb:cart-timer-expired',{detail:{cart,type:'roomboss'}}))}
function handleBedbankExpiration(cart){writeCart({items:[]});clearTimerMeta();setProceedVisible(!1);showRefresh(!1);showExpiredMessage('Your reservation period has expired. Please re-select your rooms to continue.');document.dispatchEvent(new CustomEvent('rb:cart-timer-expired',{detail:{cart:{items:[]},type:'bedbank'}}));document.dispatchEvent(new CustomEvent('rb:cart-updated',{detail:{cart:{items:[]},expired:!0}}))}
function collectRefreshItems(cart){return(cart.items||[]).map((it)=>({property_id:it.hotel_type_id||it.property_id,room_id:it.room_type_id||0,rate_plan_id:it.rateplan_id||it.ratePlanId||'',check_in:it.dates?.check_in||it.check_in||it.dates?.checkin||'',check_out:it.dates?.check_out||it.check_out||it.dates?.checkout||'',adults:it.guests?.adults||1,children:it.guests?.children||0,infants:it.guests?.infants||0,}))}
function refreshAvailability(button){const cart=readCart();if(!cart.items||!cart.items.length)return;if(button){button.disabled=!0;const originalHtml=button.innerHTML;button.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Refreshing…';const restoreButton=()=>{button.disabled=!1;button.innerHTML=originalHtml};runRefresh(cart,restoreButton)}else{runRefresh(cart,null)}}
function runRefresh(cart,restoreButton){const items=collectRefreshItems(cart);if(typeof kv_object==='undefined'||!kv_object.ajaxurl){if(restoreButton)restoreButton();return}
const body=new URLSearchParams({action:'kv_refresh_cart_availability',items:JSON.stringify(items),});fetch(kv_object.ajaxurl+'?v='+new Date().getTime(),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,}).then((r)=>r.json()).then((res)=>{if(!res||!res.success||!res.data){throw new Error('invalid_response')}
const data=res.data;const unavailable=Array.isArray(data.unavailable)?data.unavailable:[];if(unavailable.length===0){startTimer(cart).then(()=>{setProceedVisible(!0);showRefresh(!1);startTick();updateTimerUI();if(restoreButton)restoreButton()});return}
const removedIndexes=unavailable.map((u)=>Number(u.index)).filter((n)=>!isNaN(n));removedIndexes.sort((a,b)=>b-a);const remaining=(cart.items||[]).filter((_,idx)=>!removedIndexes.includes(idx));const newCart={items:remaining};writeCart(newCart);if(!remaining.length){clearTimerMeta();showExpiredMessage('Unfortunately, the rooms in your cart are no longer available. Please re-select your rooms.');document.dispatchEvent(new CustomEvent('rb:cart-timer-expired',{detail:{cart:newCart,type:'roomboss'}}));document.dispatchEvent(new CustomEvent('rb:cart-updated',{detail:{cart:newCart,refreshed:!0}}))}else{startTimer(newCart).then(()=>{setProceedVisible(!0);showRefresh(!1);startTick();updateTimerUI();document.dispatchEvent(new CustomEvent('rb:cart-updated',{detail:{cart:newCart,refreshed:!0}}))})}
if(restoreButton)restoreButton()}).catch(()=>{if(restoreButton)restoreButton()})}
let tickInterval=null;function updateTimerUI(){const cart=readCart();const item=getPrimaryItem(cart);if(!item){hideTimerNode();return}
const meta=readTimerMeta();if(!meta){const placeholder=ensureTimerNode(cart);if(placeholder){const cd=placeholder.querySelector('[data-role="countdown"]');if(cd&&cd.textContent==='--:--'){cd.textContent='--:--'}
placeholder.classList.remove('is-expired')}
startTimer(cart);return}
const node=ensureTimerNode(cart);if(!node){return}
const countdownEl=node.querySelector('[data-role="countdown"]');if(countdownEl){countdownEl.textContent=formatRemaining(remainingMs(meta))}
if(isExpired(meta)){if(meta.is_bedbank){handleBedbankExpiration(cart)}else{console.log('Cart timer expired (RoomBoss). Proceed buttons hidden, Refresh Availability shown.');handleRoombossExpiration(cart)}
stopTick()}else{node.classList.remove('is-expired');setProceedVisible(!0);showRefresh(!1)}}
function hideTimerNode(){const node=document.querySelector(TIMER_SELECTOR);if(node)node.style.display='none'}
function mount(){const cart=readCart();if(!cart.items||!cart.items.length){hideTimerNode();return!1}
const node=ensureTimerNode(cart);if(!node){return!1}
if(node.style){node.style.display=''}
setProceedVisible(!0);startTick();return!0}
function mountWhenReady(timeoutMs=15000){if(findCartContainer()){mount();return()=>{}}
let timeoutId=null;const observer=new MutationObserver(()=>{if(findCartContainer()){observer.disconnect();if(timeoutId)clearTimeout(timeoutId);mount()}});observer.observe(document.documentElement,{childList:!0,subtree:!0,});timeoutId=setTimeout(()=>{observer.disconnect()},timeoutMs);return()=>{observer.disconnect();if(timeoutId)clearTimeout(timeoutId)}}
function startTick(){if(tickInterval)return;updateTimerUI();tickInterval=setInterval(updateTimerUI,1000)}
function stopTick(){if(tickInterval){clearInterval(tickInterval);tickInterval=null}}
let mountObserverTeardown=null;function bindGlobalEvents(){document.addEventListener('click',(e)=>{const target=e.target.closest('[data-role="refresh"]');if(!target)return;e.preventDefault();refreshAvailability(target)});document.addEventListener('rb:cart-updated',(e)=>{const cart=(e&&e.detail&&e.detail.cart)?e.detail.cart:readCart();if(!cart.items||!cart.items.length){clearTimerMeta();hideTimerNode();setProceedVisible(!1);return}
setProceedVisible(!0);const node=ensureTimerNode(cart);if(node&&node.style)node.style.display='';startTick();startTimer(cart).then(()=>{updateTimerUI();startTick()})});document.addEventListener('rb:booking-ui-ready',()=>{mount()});window.addEventListener('storage',(e)=>{if(e.key===STORAGE_KEY||e.key===TIMER_META_KEY){updateTimerUI()}})}
function init(){bindGlobalEvents();const cart=readCart();if(!cart.items||!cart.items.length){clearTimerMeta();hideTimerNode();return}
if(findCartContainer()){const meta=readTimerMeta();if(!meta){startTimer(cart).then(()=>mount())}else if(isExpired(meta)){if(meta.is_bedbank){handleBedbankExpiration(cart)}else{mount()}}else{mount()}
return}
if(mountObserverTeardown){mountObserverTeardown();mountObserverTeardown=null}
mountObserverTeardown=mountWhenReady()}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init)}else{init()}
window.KVCartTimer={init,mount,mountWhenReady,startTimer,refreshAvailability,isExpired,remainingMs,formatRemaining,DEFAULTS,}})()