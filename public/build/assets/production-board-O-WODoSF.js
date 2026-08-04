import{d as r,e as n}from"./data-table-BiSrnXne.js";let s=null;document.addEventListener("DOMContentLoaded",()=>{s=window.productionBoardConfig,s&&(document.getElementById("production-search")?.addEventListener("input",r(()=>d(),300)),d())});async function d(){const t=new URLSearchParams({search:document.getElementById("production-search")?.value??""});try{const a=await fetch(`${s.dataUrl}?${t.toString()}`,{headers:{Accept:"application/json"}}),e=await a.json();if(!a.ok||!e.success)throw new Error(e.message||"Unable to load production board.");c(e.columns??[])}catch(a){const e=document.getElementById("production-columns");e&&(e.innerHTML=`<p class="text-[13px] text-red-600">${n(a.message||"Unable to load production board.")}</p>`)}}function c(t){const a=document.getElementById("production-columns");if(a){if(!t.length){a.innerHTML='<p class="text-[13px] text-gray-500">No production stages configured.</p>';return}a.innerHTML=t.map(e=>`
        <div class="min-w-[240px] max-w-[280px] flex-1 rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-3 py-2">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-[13px] font-semibold text-gray-900">${n(e.label)}</h3>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">${e.count}</span>
                </div>
            </div>
            <div class="space-y-2 p-2">
                ${(e.orders??[]).length?e.orders.map(o=>i(o)).join(""):'<p class="px-1 py-3 text-[12px] text-gray-400">No orders</p>'}
            </div>
        </div>
    `).join(""),a.querySelectorAll("[data-advance]").forEach(e=>{e.addEventListener("click",()=>p(e.dataset.advance))})}}function i(t){const a=t.production_blocked?'<span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700">Blocked</span>':t.due_date?`<span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">Due ${n(t.due_date)}</span>`:"";return`
        <div class="rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <a href="${n(t.show_url)}" class="text-[13px] font-semibold text-brand hover:underline">${n(t.order_number)}</a>
                ${a}
            </div>
            <p class="mt-1 text-[12px] text-gray-700">${n(t.customer_name)}</p>
            <p class="mt-1 text-[11px] text-gray-500">${n(t.status_label)} · ${n(String(t.item_count))} items</p>
            ${t.can_advance?`<button type="button" data-advance="${t.id}" class="mt-2 inline-flex h-8 items-center rounded-md bg-brand px-2.5 text-[12px] font-medium text-white hover:bg-brand-hover">Advance</button>`:""}
        </div>
    `}async function p(t){if(!confirm("Advance this job to the next production stage?"))return;const a=`${s.advanceUrlBase}/${t}/advance`;try{let e;if(typeof window.postData=="function")e=await window.postData(a);else{const o=await fetch(a,{method:"POST",headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.content??""},body:"{}"});if(e=await o.json(),!o.ok||!e.success)throw new Error(e.message||"Unable to advance stage.")}typeof showToast=="function"&&showToast(e.production_stage_label?`Moved to ${e.production_stage_label}`:"Stage advanced."),d()}catch(e){typeof showToast=="function"?showToast(e.message||"Unable to advance stage.","error"):alert(e.message||"Unable to advance stage.")}}
