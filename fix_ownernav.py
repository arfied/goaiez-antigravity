import sys
content = open("app/app/Support/Account/OwnerNav.php").read()
content = content.replace("""<<<<<<< HEAD
            OwnerNavItem::make('Demand in your area', 'x-130.public-index-pages', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Demand by trade', 'x-130.coverage-by-trade', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('WhatsApp templates', 'c-whatsapp.template-status-card', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Template approval queue', 'c-whatsapp.template-approval-queue', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Data coming in', 'x-156.ingest-volume-by', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Rows we could not take', 'x-156.rejectedrows-list', OwnerNavItem::GROUP_MORE),
=======
            OwnerNavItem::make('Onboarding checks', 'x-118.groundcheck', OwnerNavItem::GROUP_MORE),
>>>>>>> origin/track/sixty""", """            OwnerNavItem::make('Demand in your area', 'x-130.public-index-pages', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Demand by trade', 'x-130.coverage-by-trade', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('WhatsApp templates', 'c-whatsapp.template-status-card', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Template approval queue', 'c-whatsapp.template-approval-queue', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Data coming in', 'x-156.ingest-volume-by', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Rows we could not take', 'x-156.rejectedrows-list', OwnerNavItem::GROUP_MORE),
            OwnerNavItem::make('Onboarding checks', 'x-118.groundcheck', OwnerNavItem::GROUP_MORE),""")
open("app/app/Support/Account/OwnerNav.php", "w").write(content)
