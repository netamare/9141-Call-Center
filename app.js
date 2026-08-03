const events=[
 {title:'Multi-vehicle collision on Mombasa Road',meta:'07:18 • Mombasa Road, near City Cabanas',priority:'critical',status:'New',time:'2 min ago'},
 {title:'Power outage at Githurai Market',meta:'07:11 • Githurai 45',priority:'high',status:'Assigned',time:'9 min ago'},
 {title:'Flooding reported in South C',meta:'06:58 • South C, Muhoho Avenue',priority:'high',status:'In progress',time:'22 min ago'},
 {title:'Medical emergency at CBD bus stop',meta:'06:42 • Tom Mboya Street',priority:'medium',status:'Assigned',time:'38 min ago'}
];
const activity=[['☎','coral','Call received from 9141','Collision event created automatically','2 min ago'],['♙','blue','Alpha-3 dispatched','Team assigned to INC-24081','4 min ago'],['✓','violet','Incident INC-24074 resolved','Power restoration confirmed','18 min ago'],['↗','amber','Escalated to county response','Flooding incident raised to high','25 min ago']];
const list=document.getElementById('eventList');
function renderEvents(){list.innerHTML=events.map(e=>`<div class="event"><span class="event-priority ${e.priority}"></span><div><h3>${e.title}</h3><p>${e.meta}</p></div><div class="event-status"><span class="status ${e.status.toLowerCase().replace(' ','')}">${e.status}</span><br>${e.time}</div></div>`).join('')}
document.getElementById('activityList').innerHTML=activity.map(a=>`<div class="activity"><span class="activity-icon ${a[1]}">${a[0]}</span><div><p><strong>${a[2]}</strong><br>${a[3]}</p><time>${a[4]}</time></div></div>`).join('');renderEvents();
const modal=document.getElementById('modalBackdrop');document.getElementById('newEventButton').onclick=()=>modal.hidden=false;document.getElementById('closeModal').onclick=()=>modal.hidden=true;modal.onclick=e=>{if(e.target===modal)modal.hidden=true};
document.getElementById('eventForm').onsubmit=e=>{e.preventDefault();let form=new FormData(e.target),title=form.get('Event title')||e.target.querySelector('input').value,priority=e.target.querySelector('select').value;events.unshift({title,meta:`Just now • ${e.target.querySelectorAll('input')[1].value}`,priority:priority.toLowerCase(),status:'New',time:'just now'});document.getElementById('activeEvents').textContent=12+events.length-4;renderEvents();modal.hidden=true;e.target.reset()};
document.getElementById('acknowledge').onclick=function(){this.textContent='Acknowledged';this.style.background='#28b89a';document.querySelector('.hero-alert').style.borderLeftColor='#28b89a';document.getElementById('alertTimer').textContent='Acknowledged'};
document.getElementById('menuButton').onclick=()=>document.getElementById('sidebar').classList.toggle('open');
