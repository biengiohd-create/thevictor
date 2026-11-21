export const limitVelocity=v=>Math.max(-3.5,Math.min(3.5,v));
export function coastStep(vx,vy,elapsed){const dt=Math.max(0,Math.min(64,elapsed)),decay=Math.exp(-dt/280);return {dx:vx*280*(1-decay),dy:vy*280*(1-decay),vx:vx*decay,vy:vy*decay};}
export class TouchMotion{
 begin(x,y,time){this.x=this.startX=x;this.y=this.startY=y;this.time=time;this.vx=this.vy=0;this.dragged=false;}
 move(x,y,time){const dx=this.x-x,dy=this.y-y,dt=Math.max(1,time-this.time);this.x=x;this.y=y;this.time=time;if(!this.dragged&&Math.hypot(x-this.startX,y-this.startY)<8)return null;this.dragged=true;this.vx=limitVelocity(.7*dx/dt+.3*this.vx);this.vy=limitVelocity(.7*dy/dt+.3*this.vy);return {dx,dy};}
}
