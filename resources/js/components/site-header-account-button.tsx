import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { initials } from '@/lib/format';
import type { AuthUser } from '@/types';

/** Account trigger; also rendered as a static placeholder until the dropdown chunk loads. */
export function AccountButton({ user, ...props }: { user: AuthUser } & React.ComponentProps<typeof Button>) {
    return (
        <Button variant="ghost" className="gap-2 pr-1.5 pl-1" aria-label="Menu akun" {...props}>
            <Avatar className="size-6">
                {user.avatar && <AvatarImage src={user.avatar} alt="" />}
                <AvatarFallback className="text-[10px]">{initials(user.name)}</AvatarFallback>
            </Avatar>
            <span className="hidden max-w-32 truncate text-sm sm:inline">{user.name}</span>
        </Button>
    );
}
