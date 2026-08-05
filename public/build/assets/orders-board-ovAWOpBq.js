import{d as p,e as r}from"./data-table-BiSrnXne.js";let o=null,d=null,s=null,u=null;document.addEventListener("DOMContentLoaded",()=>{o=window.ordersBoardConfig,o&&(y(),x(),i())});function x(){document.getElementById("board-search")?.addEventListener("input",p(()=>i(),300)),document.getElementById("board-source-filter")?.addEventListener("change",()=>i())}function y(){const e=document.getElementById("board-action-modal");!e||e.dataset.initialized==="true"||(e.dataset.initialized="true",e.addEventListener("click",t=>{t.target===e&&g()}),e.querySelectorAll("[data-close]").forEach(t=>{t.addEventListener("click",()=>g())}),document.getElementById("board-action-confirm")?.addEventListener("click",()=>B()))}async function i(){const e=new URLSearchParams({search:document.getElementById("board-search")?.value??"",source:document.getElementById("board-source-filter")?.value??""});try{const t=await fetch(`${o.dataUrl}?${e.toString()}`,{headers:{Accept:"application/json"}}),a=await t.json();if(!t.ok||!a.success)throw new Error(a.message||"Unable to load board.");h(a.attention??[]),v(a.columns??[]),_(a.columns??[])}catch(t){document.getElementById("board-attention").innerHTML=`<p class="px-2 py-3 text-[13px] text-red-600">${r(t.message||"Unable to load board.")}</p>`,document.getElementById("board-columns").innerHTML=`<p class="text-[13px] text-red-600">${r(t.message||"Unable to load board.")}</p>`}}function h(e){const t=document.getElementById("board-attention");if(t){if(!e.length){t.innerHTML='<p class="px-2 py-3 text-[13px] text-gray-500">No shortage or draft PO blockers right now.</p>';return}t.innerHTML=e.map(a=>`
        <a href="${r(a.show_url)}" class="flex items-start justify-between gap-3 rounded-md px-2 py-2 hover:bg-gray-50">
            <div class="min-w-0">
                <p class="truncate text-[13px] font-medium text-gray-900">${r(a.order_number)} · ${r(a.customer_name)}</p>
                <p class="mt-0.5 text-[12px] text-gray-500">${r($(a))}</p>
            </div>
            <div class="flex shrink-0 flex-wrap justify-end gap-1">
                ${a.has_shortage?l("Shortage","bg-amber-100 text-amber-800"):""}
                ${a.has_draft_po?l("Draft PO","bg-gray-100 text-gray-700"):""}
            </div>
        </a>
    `).join("")}}function $(e){const t=[];return e.has_shortage&&t.push(`${e.shortage_qty} pcs short`),e.has_draft_po&&t.push(`Draft PO ${e.po_number}`),t.join(" · ")||e.status_label}function v(e){const t=document.getElementById("board-pulse");t&&(t.innerHTML=e.map(a=>`
        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2">
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">${r(a.label)}</p>
            <p class="mt-1 text-lg font-semibold text-gray-900">${r(String(a.count))}</p>
        </div>
    `).join(""))}function _(e){const t=document.getElementById("board-columns");t&&(t.innerHTML=e.map(a=>`
        <section
            class="board-column flex w-72 shrink-0 flex-col rounded-lg border border-gray-200 bg-gray-50"
            data-status="${r(a.status)}"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2">
                <h3 class="text-[13px] font-semibold text-gray-900">${r(a.label)}</h3>
                <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-gray-700">${r(String(a.count))}</span>
            </div>
            <div class="board-column-body min-h-40 flex-1 space-y-2 overflow-y-auto p-2" data-status="${r(a.status)}">
                ${(a.orders??[]).map(n=>w(n)).join("")||'<p class="px-1 py-4 text-center text-[12px] text-gray-400">No orders</p>'}
            </div>
        </section>
    `).join(""),L())}function w(e){const t=(e.allowed_targets??[]).length>0;return`
        <article
            class="board-card rounded-md border border-gray-200 bg-white p-3 shadow-sm ${t?"cursor-grab active:cursor-grabbing":"opacity-95"}"
            draggable="${t?"true":"false"}"
            data-order-id="${e.id}"
            data-status="${r(e.status)}"
            data-allowed-targets="${r((e.allowed_targets??[]).join(","))}"
            data-can-fulfill="${e.can_fulfill?"1":"0"}"
            data-can-cancel="${e.can_cancel?"1":"0"}"
            data-order-number="${r(e.order_number)}"
        >
            <div class="flex items-start justify-between gap-2">
                <a href="${r(e.show_url)}" class="truncate text-[13px] font-semibold text-gray-900 hover:underline">${r(e.order_number)}</a>
                ${E(e.status,e.status_label)}
            </div>
            <p class="mt-1 truncate text-[12px] text-gray-700">${r(e.customer_name)}</p>
            <p class="mt-0.5 text-[11px] text-gray-500">${r(String(e.item_count))} items · ${r(e.created_at)}</p>
            <div class="mt-2 flex flex-wrap gap-1">
                ${e.customer_source_label?`<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${e.customer_source_badge_color}">${r(e.customer_source_icon??"")} ${r(e.customer_source_label)}</span>`:""}
                ${e.has_shortage?l(`Short ${e.shortage_qty}`,"bg-amber-100 text-amber-800"):""}
                ${e.po_number?l(e.po_number,e.has_draft_po?"bg-gray-100 text-gray-700":"bg-blue-100 text-blue-800"):""}
            </div>
        </article>
    `}function E(e,t){return l(t,{pending:"bg-gray-100 text-gray-700",reserved:"bg-blue-100 text-blue-800",partially_reserved:"bg-amber-100 text-amber-800",fulfilled:"bg-green-100 text-green-800",cancelled:"bg-red-100 text-red-800"}[e]??"bg-gray-100 text-gray-700")}function l(e,t){return`<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${t}">${r(e)}</span>`}function L(){document.querySelectorAll('.board-card[draggable="true"]').forEach(e=>{e.addEventListener("dragstart",t=>{s=Number(e.dataset.orderId),u=e.dataset.status,e.classList.add("opacity-60"),t.dataTransfer.effectAllowed="move",t.dataTransfer.setData("text/plain",String(s))}),e.addEventListener("dragend",()=>{e.classList.remove("opacity-60"),m(),s=null,u=null})}),document.querySelectorAll(".board-column-body").forEach(e=>{e.addEventListener("dragover",t=>{t.preventDefault();const a=e.dataset.status;if(!f(a)){t.dataTransfer.dropEffect="none";return}t.dataTransfer.dropEffect="move",e.classList.add("ring-2","ring-blue-300","ring-inset")}),e.addEventListener("dragleave",()=>{e.classList.remove("ring-2","ring-blue-300","ring-inset")}),e.addEventListener("drop",t=>{t.preventDefault(),m();const a=e.dataset.status,n=document.querySelector(`.board-card[data-order-id="${s}"]`);if(!(!n||!f(a)||a===u)){if(a==="fulfilled"){b({orderId:s,orderNumber:n.dataset.orderNumber,action:"fulfill",title:"Fulfill order?",message:`Fulfill ${n.dataset.orderNumber} and deduct reserved stock?`});return}a==="cancelled"&&b({orderId:s,orderNumber:n.dataset.orderNumber,action:"cancel",title:"Cancel order?",message:`Cancel ${n.dataset.orderNumber} and release reserved stock?`})}})})}function f(e){if(!s)return!1;const t=document.querySelector(`.board-card[data-order-id="${s}"]`);return!(!t||!(t.dataset.allowedTargets??"").split(",").filter(Boolean).includes(e)||e==="fulfilled"&&t.dataset.canFulfill!=="1"||e==="cancelled"&&t.dataset.canCancel!=="1")}function m(){document.querySelectorAll(".board-column-body").forEach(e=>{e.classList.remove("ring-2","ring-blue-300","ring-inset")})}function b({orderId:e,orderNumber:t,action:a,title:n,message:c}){d={orderId:e,orderNumber:t,action:a},document.getElementById("board-action-title").textContent=n,document.getElementById("board-action-message").textContent=c,document.getElementById("board-action-modal")?.classList.remove("hidden")}function g(){d=null,document.getElementById("board-action-modal")?.classList.add("hidden")}async function B(){if(!d)return;const{orderId:e,action:t}=d,a=document.getElementById("board-action-confirm"),n=t==="fulfill"?`${o.fulfillUrlBase}/${e}/fulfill`:`${o.cancelUrlBase}/${e}/cancel`;setButtonLoading(a,!0,"Working...");try{await postData(n),showToast(t==="fulfill"?"Order fulfilled.":"Order cancelled."),g(),await i()}catch(c){showToast(c.message||"Unable to update order.","error")}finally{setButtonLoading(a,!1)}}
