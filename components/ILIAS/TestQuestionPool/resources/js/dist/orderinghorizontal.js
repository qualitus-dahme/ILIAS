/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 */
!function(e){"use strict";const t="{::}",n="answers",o="c-test__dropzone";function s(e,t){!function(e){const t=e.querySelector(`.${o}`);e.querySelectorAll(`.${n}`).forEach((e=>{e.previousElementSibling?.classList.contains(o)||e.parentNode.insertBefore(t.cloneNode(),e),e.nextElementSibling?.classList.contains(o)||e.parentNode.insertBefore(t.cloneNode(),e.nextElementSibling)})),e.querySelectorAll(`.${o} + .${o}`).forEach((e=>{e.remove()}))}(t),e.previousElementSibling?.classList.contains(o)&&e.previousElementSibling.remove(),e.nextElementSibling?.classList.contains(o)&&e.nextElementSibling.remove()}function r(e,r){!function(e){const t=e.querySelectorAll(`.${n}`);let s=0;t.forEach((e=>{s+=e.offsetWidth})),e.querySelectorAll(`.${o}`).forEach((e=>{e.style.width=s/t.length+"px",e.style.height=`${t.item(0).offsetHeight}px`}))}(e),r("move",e,n,o,(()=>{!function(e){const o=[];e.querySelectorAll(`.${n} > div > span`).forEach((e=>{o.push(e.textContent)})),e.nextElementSibling.value=o.join(t)}(e)}),(t=>{s(t,e)}))}const i="c-test__dropzone--active",l="c-test__dropzone--hover";function c(e,t,n,o,s,r){let c,a,d;function f(t){setTimeout((()=>{h(t.target),t.dataTransfer.dropEffect=e,t.dataTransfer.effectAllowed=e,t.dataTransfer.setDragImage(d,0,0)}),0)}function u(e){e.preventDefault(),e.stopPropagation(),h(e.target.closest(`.${n}`));const t=d.offsetWidth,o=d.offsetHeight;c=d.cloneNode(!0),d.parentNode.insertBefore(c,d),d.style.position="fixed",d.style.left=e.touches[0].clientX-t/2+"px",d.style.top=e.touches[0].clientY-o/2+"px",d.style.width=`${t}px`,d.style.height=`${o}px`,d.addEventListener("touchmove",v),d.addEventListener("touchend",y)}function h(e){d=e,d.style.opacity=.5,r(d),t.querySelectorAll(`.${o}`).forEach((e=>{x(e),e.classList.add(i)})),d.querySelectorAll(`.${o}`).forEach((e=>{e.classList.remove(i)}))}function v(e){e.preventDefault(),d.style.left=e.touches[0].clientX-d.offsetWidth/2+"px",d.style.top=e.touches[0].clientY-d.offsetHeight/2+"px";const{documentElement:n}=t.ownerDocument;e.touches[0].clientY>.8*n.clientHeight&&n.scroll({left:0,top:.8*e.touches[0].pageY,behavior:"smooth"}),e.touches[0].clientY<.2*n.clientHeight&&n.scroll({left:0,top:.8*e.touches[0].pageY,behavior:"smooth"});const s=t.ownerDocument.elementsFromPoint(e.changedTouches[0].clientX,e.changedTouches[0].clientY).filter((e=>e.classList.contains(o)));0===s.length&&void 0!==a&&(a.classList.remove(l),a=void 0),1===s.length&&a!==s[0]&&(void 0!==a&&a.classList.remove(l),[a]=s,a.classList.add(l))}function g(e){e.preventDefault()}function m(e){e.target.classList.add(l)}function p(e){e.target.classList.remove(l)}function E(){d.removeAttribute("style"),t.querySelectorAll(`.${o}`).forEach((e=>{e.classList.remove(i),e.classList.remove(l)}))}function L(e){e.preventDefault(),S(e.target)}function y(e){e.preventDefault();const n=t.ownerDocument.elementsFromPoint(e.changedTouches[0].clientX,e.changedTouches[0].clientY).filter((e=>e.classList.contains(o)));E(),c.remove(),1===n.length&&S(n[0])}function S(t){const n=d.parentNode;let o=d;"move"!==e&&(o=d.cloneNode(!0),o.style.opacity=null,$(o)),t.parentNode.insertBefore(o,t),s(o,t,d,n)}function $(e){e.addEventListener("dragstart",f),e.addEventListener("dragend",E),e.addEventListener("touchstart",u)}function x(e){e.removeEventListener("dragover",g),e.removeEventListener("dragenter",m),e.removeEventListener("dragleave",p),e.removeEventListener("drop",L),e.addEventListener("dragover",g),e.addEventListener("dragenter",m),e.addEventListener("dragleave",p),e.addEventListener("drop",L)}t.querySelectorAll(`.${n}`).forEach($),t.querySelectorAll(`.${o}`).forEach(x)}e.test=e.test||{},e.test.orderinghorizontal=e.test.orderinghorizontal||{},e.test.orderinghorizontal.init=e=>r(e,c)}(il);
