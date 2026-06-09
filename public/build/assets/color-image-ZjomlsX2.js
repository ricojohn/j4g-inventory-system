import{e as n}from"./data-table-BbGHUeVY.js";const d=`<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-gray-100 text-gray-400" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 9h.008v.008H18V9zm.75 9.75H5.25A2.25 2.25 0 013 16.5V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v9a2.25 2.25 0 01-2.25 2.25z" />
    </svg>
</span>`;function c({imageUrl:e="",colorName:t="",itemCode:o="",subtitle:i=null}){const r=i??(o?`${o} · ${t}`:t),s=e?`<img src="${n(e)}" alt="${n(t)}" class="h-9 w-9 shrink-0 rounded object-cover ring-1 ring-gray-200">`:d;return`
        <button
            type="button"
            class="color-image-view-trigger flex items-center gap-2 text-left hover:opacity-80"
            data-image-url="${n(e)}"
            data-subtitle="${n(r)}"
            title="View color image"
        >
            ${s}
            <span class="text-gray-700">${n(t)}</span>
        </button>
    `}function g({imageUrl:e="",colorName:t="",itemCode:o="",subtitle:i=null,disabled:r=!1}){const s=i??(o?`${o} · ${t}`:t),l=r?"disabled":"";return`
        <button
            type="button"
            class="color-image-view-trigger inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-gray-200 text-gray-500 ${r?"cursor-not-allowed opacity-40":"hover:bg-gray-100"}"
            data-image-url="${n(e)}"
            data-subtitle="${n(s)}"
            title="View color image"
            ${l}
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 9h.008v.008H18V9zm.75 9.75H5.25A2.25 2.25 0 013 16.5V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v9a2.25 2.25 0 01-2.25 2.25z" />
            </svg>
        </button>
    `}function a({imageUrl:e="",subtitle:t=""}){const o=document.getElementById("color-image-view-modal"),i=document.getElementById("color-image-view-preview"),r=document.getElementById("color-image-view-empty"),s=document.getElementById("color-image-view-subtitle");!o||!i||!r||(s&&(s.textContent=t),e?(i.src=e,i.classList.remove("hidden"),r.classList.add("hidden")):(i.src="",i.classList.add("hidden"),r.classList.remove("hidden")),o.classList.remove("hidden"))}function u(){const e=document.getElementById("color-image-view-modal");!e||e.dataset.initialized==="true"||(e.dataset.initialized="true",e.addEventListener("click",t=>{t.target===e&&e.classList.add("hidden")}),e.querySelectorAll("[data-close]").forEach(t=>{t.addEventListener("click",()=>e.classList.add("hidden"))}),document.addEventListener("click",t=>{const o=t.target.closest(".color-image-view-trigger");!o||o.disabled||(t.preventDefault(),a({imageUrl:o.dataset.imageUrl??"",subtitle:o.dataset.subtitle??""}))}))}window.renderColorImageTrigger=c;window.renderColorImageIconButton=g;window.openColorImageViewModal=a;window.initColorImageViewModal=u;export{u as i,g as r};
