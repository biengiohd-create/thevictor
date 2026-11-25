export function canWorkbook(user,right){return !!user&&!(user.sourceReadOnly&&['edit','restore','board'].includes(right))&&(!!user.owner||(user.rights||[]).includes(right));}
