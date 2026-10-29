const $ = id => document.getElementById(id);
const gamesContainer=$('gamesContainer'), categoryList=$('categoryList'), reviewContainer=$('reviewContainer');
let games=[],categories=[],reviews=[],activeCategory=null,currentUser=null;
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function api(action,method='GET',payload=null){const opts={method,headers:{Accept:'application/json'}};if(payload!==null){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(payload);}const r=await fetch(`api.php?action=${encodeURIComponent(action)}`,opts);const d=await r.json();if(!r.ok)throw Error(d.error||'Wystąpił błąd.');return d;}
function message(el,text,error=false){el.textContent=text;el.classList.toggle('error',error);el.classList.toggle('success',!error&&!!text);}
function renderAuth(){const wrap=$('reviewFormWrap'),hint=$('reviewLoginHint');if(wrap)wrap.hidden=!currentUser;if(hint)hint.hidden=!!currentUser;}
function renderCategories(){categoryList.innerHTML='<button type="button" data-category-id="all">🎮 Wszystkie</button>'+categories.map(c=>`<button type="button" data-category-id="${Number(c.id)}">${esc(c.icon||'🎮')} ${esc(c.name)}</button>`).join('');}
function renderGames(){const q=$('searchInput').value.trim().toLocaleLowerCase('pl');const list=games.filter(g=>(activeCategory===null||Number(g.category_id)===activeCategory)&&String(g.name).toLocaleLowerCase('pl').includes(q));gamesContainer.innerHTML=list.map(g=>`<article class="game-card">${g.image?`<img src="${esc(g.image)}" alt="${esc(g.name)}" loading="lazy">`:''}<div class="game-info"><span class="category">${esc(g.category)}</span><h3>${esc(g.name)}</h3><p>${esc(g.description)}</p><div class="rating">⭐ ${Number(g.rating||0).toFixed(1)}/10</div></div></article>`).join('');$('noResults').hidden=list.length>0;$('statusMessage').hidden=true;}
function renderGameOptions(){ $('reviewGame').innerHTML=games.map(g=>`<option value="${Number(g.id)}">${esc(g.name)}</option>`).join(''); }
function renderReviews(){reviewContainer.innerHTML=reviews.length?reviews.map(r=>`<article class="review"><div class="review-top"><span>🎮 ${esc(r.game_name)}</span><strong>${Number(r.rating).toFixed(1)}/10</strong></div><p>„${esc(r.content)}”</p><small>Autor: ${esc(r.author)}</small></article>`).join(''):'<p>Brak recenzji w bazie danych. Możesz dodać pierwszą!</p>';}
async function refreshReviews(){reviews=(await api('reviews')).reviews||[];renderReviews();}
async function load(){try{const [all,me]=await Promise.all([api('all'),api('me')]);games=all.games||[];categories=all.categories||[];reviews=all.reviews||[];currentUser=me.user||null;renderCategories();renderGameOptions();renderGames();renderReviews();renderAuth();}catch(e){$('statusMessage').hidden=false;$('statusMessage').textContent=e.message;console.error(e);}}



if($('reviewForm')) $('reviewForm').addEventListener('submit',async e=>{e.preventDefault();const form=e.currentTarget;const f=new FormData(form);try{const d=await api('add_review','POST',{game_id:Number(f.get('game_id')),rating:Number(f.get('rating')),content:f.get('content')});form.reset();message($('reviewMessage'),d.message);await refreshReviews();}catch(err){message($('reviewMessage'),err.message,true);}});
categoryList.addEventListener('click',e=>{const b=e.target.closest('button[data-category-id]');if(!b)return;activeCategory=b.dataset.categoryId==='all'?null:Number(b.dataset.categoryId);renderGames();});
$('searchForm').addEventListener('submit',e=>{e.preventDefault();renderGames();});$('searchInput').addEventListener('input',renderGames);
load();
