// Render-only: retain text identifiers and every unrelated marker.
export function withoutNumericTextMarker(cell){
 if(String(cell?.markers?.tl?.color??'').toLowerCase()!=='#409f11')return cell;
 const {tl,...markers}=cell.markers;
 return {...cell,markers};
}
