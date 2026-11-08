/* Clients in the admin. Core WordPress owns editor state, uploads and saves. */
(function () {
 'use strict';
 if (!window.pencilClient) return;
 if (window.wp?.domReady && window.wp?.data) {
  window.wp.domReady(() => { const preferences=window.wp.data.dispatch('core/preferences'); if(preferences?.set){preferences.set('core/edit-post','welcomeGuide',false);preferences.set('core/edit-post','welcomeGuideTemplate',false);} });
 }
 // Back to website leads to the page the client came from.
 const backLinks = [document.querySelector('#wp-admin-bar-pencil-switch > a')].filter(Boolean);
 const key = 'pencilino-client-return';
 try {
  const supplied = new URLSearchParams(location.search).get('pencil_return');
  if (supplied) {
   const url = new URL(supplied, location.origin);
   if (url.origin === location.origin && !url.pathname.includes('/wp-admin/')) sessionStorage.setItem(key, url.href);
  }
  const saved = sessionStorage.getItem(key);
  if (saved) {
   const url = new URL(saved);
   if (url.origin === location.origin && !url.pathname.includes('/wp-admin/')) { url.searchParams.delete('pencil-edit'); backLinks.forEach(link => { link.href = url.href; }); }
  }
 } catch (e) { /* Navigation still works without browser storage. */ }
 let replay = false;
 document.addEventListener('click', function (event) {
  if (replay) return;
  const target = event.target.closest('a.submitdelete, .row-actions .trash a, button.editor-post-trash, .editor-post-trash button');
  if (!target) return;
  event.preventDefault(); event.stopImmediatePropagation();
  const previous = document.activeElement;
  const layer = document.createElement('div'); layer.className = 'pencil-client-dialog-backdrop';
  const dialog = document.createElement('section'); dialog.className = 'pencil-client-dialog'; dialog.setAttribute('role','dialog');dialog.setAttribute('aria-modal','true');dialog.setAttribute('aria-labelledby','pencil-client-trash-title');
  const title = document.createElement('h2'); title.id='pencil-client-trash-title'; title.textContent=pencilClient.question;
  const text = document.createElement('p'); text.textContent=pencilClient.description;
  const actions = document.createElement('div');actions.className='pencil-client-dialog-actions';
  const trash=document.createElement('button');trash.type='button';trash.className='pencil-client-button pencil-client-button--primary';trash.textContent=pencilClient.trash;
  const keep=document.createElement('button');keep.type='button';keep.className='pencil-client-button';keep.textContent=pencilClient.keep;
  const close=()=>{layer.remove();previous?.focus();};
  keep.addEventListener('click',close);
  trash.addEventListener('click',()=>{close();replay=true;target.click();replay=false;});
  dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();close();}if(e.key==='Tab'){if(e.shiftKey && document.activeElement===trash){e.preventDefault();keep.focus();}else if(!e.shiftKey && document.activeElement===keep){e.preventDefault();trash.focus();}}});
  actions.append(trash,keep);dialog.append(title,text,actions);layer.append(dialog);document.body.append(layer);keep.focus();
 },true);
}());
