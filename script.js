const $ = id => document.getElementById(id);
const gamesContainer=$('gamesContainer'), categoryList=$('categoryList'), reviewContainer=$('reviewContainer');
let games=[],categories=[],reviews=[],activeCategory=null,currentUser=null,favorites=new Set();
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function api(action,method='GET',payload=null){const opts={method,headers:{Accept:'application/json'}};if(payload!==null){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(payload);}const r=await fetch(`api.php?action=${encodeURIComponent(action)}`,opts);const d=await r.json();if(!r.ok)throw Error(d.error||'Wystąpił błąd.');return d;}
function message(el,text,error=false){el.textContent=text;el.classList.toggle('error',error);el.classList.toggle('success',!error&&!!text);}
function renderAuth(){const wrap=$('reviewFormWrap'),hint=$('reviewLoginHint');if(wrap)wrap.hidden=!currentUser;if(hint)hint.hidden=!!currentUser;}
function renderCategories(){categoryList.innerHTML='<button type="button" data-category-id="all">🎮 Wszystkie</button>'+categories.map(c=>`<button type="button" data-category-id="${Number(c.id)}">${esc(c.icon||'🎮')} ${esc(c.name)}</button>`).join('');}
function gameCard(g){const fav=favorites.has(Number(g.id));return `<article class="game-card">${g.image?`<img src="${esc(g.image)}" alt="${esc(g.name)}" loading="lazy">`:''}<div class="game-info"><div class="game-top"><span class="category">${esc(g.category)}</span><button class="favorite-btn ${fav?'is-favorite':''}" type="button" data-favorite-id="${Number(g.id)}" aria-label="${fav?'Usuń z ulubionych':'Dodaj do ulubionych'}: ${esc(g.name)}" aria-pressed="${fav}">${fav?'♥':'♡'}</button></div><h3>${esc(g.name)}</h3><p>${esc(g.description)}</p><div class="rating">⭐ ${Number(g.rating||0).toFixed(1)}/10</div></div></article>`;}
function renderGames(){
 const q=$('searchInput').value.trim().toLocaleLowerCase('pl');const min=Number($('minRating').value);
 const sort=$('sortGames').value;
 const list=games.filter(g=>(activeCategory===null||Number(g.category_id)===activeCategory)&&String(g.name).toLocaleLowerCase('pl').includes(q)&&Number(g.rating||0)>=min&&(!$('onlyFavorites').checked||favorites.has(Number(g.id))));
 list.sort((a,b)=>sort==='rating_desc'?Number(b.rating)-Number(a.rating):sort==='rating_asc'?Number(a.rating)-Number(b.rating):sort==='name_asc'?a.name.localeCompare(b.name,'pl'):sort==='name_desc'?b.name.localeCompare(a.name,'pl'):Number(b.id)-Number(a.id));
 gamesContainer.innerHTML=list.map(gameCard).join('');$('noResults').hidden=list.length>0;$('statusMessage').hidden=true;
 renderFavorites();
}
function renderFavorites(){const selected=games.filter(g=>favorites.has(Number(g.id)));$('favoritesContainer').innerHTML=selected.map(gameCard).join('');$('favoritesHint').textContent=selected.length?`Zapisane gry: ${selected.length}`:'Nie masz jeszcze ulubionych gier. Kliknij ♡ przy wybranej grze.';}
function renderGameOptions(){ $('reviewGame').innerHTML=games.map(g=>`<option value="${Number(g.id)}">${esc(g.name)}</option>`).join(''); }
function renderReviews(){reviewContainer.innerHTML=reviews.length?reviews.map(r=>`<article class="review"><div class="review-top"><span>🎮 ${esc(r.game_name)}</span><strong>${Number(r.rating).toFixed(1)}/10</strong></div><p>„${esc(r.content)}”</p><small>Autor: ${esc(r.author)}</small></article>`).join(''):'<p>Brak recenzji w bazie danych. Możesz dodać pierwszą!</p>';}
async function refreshReviews(){reviews=(await api('reviews')).reviews||[];renderReviews();}
async function load(){
 try{
  const [all,me]=await Promise.all([api('all'),api('me')]);
  games=all.games||[];categories=all.categories||[];reviews=all.reviews||[];currentUser=me.user||null;
  renderCategories();renderGameOptions();renderReviews();renderAuth();
  if(currentUser){try{favorites=new Set((await api('favorites')).favorites.map(Number));}catch(err){message($('reviewMessage'),'Nie można wczytać ulubionych: '+err.message,true);}}
  renderGames();
 }catch(e){$('statusMessage').hidden=false;$('statusMessage').textContent=e.message;console.error(e);}
}



if($('reviewForm')) $('reviewForm').addEventListener('submit',async e=>{e.preventDefault();const form=e.currentTarget;const f=new FormData(form);try{const d=await api('add_review','POST',{game_id:Number(f.get('game_id')),rating:Number(f.get('rating')),content:f.get('content')});form.reset();message($('reviewMessage'),d.message);await refreshReviews();}catch(err){message($('reviewMessage'),err.message,true);}});
categoryList.addEventListener('click',e=>{const b=e.target.closest('button[data-category-id]');if(!b)return;activeCategory=b.dataset.categoryId==='all'?null:Number(b.dataset.categoryId);renderGames();});
$('searchForm').addEventListener('submit',e=>{e.preventDefault();renderGames();});$('searchInput').addEventListener('input',renderGames);
load();

// Filtry są łączone: kategoria + nazwa + minimalna ocena + ulubione + sortowanie.
['minRating','sortGames','onlyFavorites'].forEach(id=>$(id).addEventListener('change',renderGames));
$('clearFilters').addEventListener('click',()=>{activeCategory=null;$('searchInput').value='';$('minRating').value='0';$('sortGames').value='newest';$('onlyFavorites').checked=false;renderGames();});
async function handleFavoriteClick(e){const btn=e.target.closest('button[data-favorite-id]');if(!btn)return;const id=Number(btn.dataset.favoriteId);btn.disabled=true;try{const d=await api('toggle_favorite','POST',{game_id:id});if(d.favorite)favorites.add(id);else favorites.delete(id);renderGames();}catch(err){alert('Nie udało się zapisać ulubionych: '+err.message);btn.disabled=false;}}
gamesContainer.addEventListener('click',handleFavoriteClick);
$('favoritesContainer').addEventListener('click',handleFavoriteClick);
