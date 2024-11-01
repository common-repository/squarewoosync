import { Link } from "react-router-dom";

import {
  ArchiveBoxIcon,
  Cog8ToothIcon,
  ClipboardDocumentListIcon,
  GiftIcon,
} from "@heroicons/react/24/outline";
import { classNames } from "../../utils/classHelper";
import useMenuFix from "../hooks/useMenuFix";

const secondaryNavigation = [
  {
    name: "General",
    href: "/settings/general",
    icon: Cog8ToothIcon,
  },
  {
    name: "Products (pro only)",
    href: "/settings/inventory",
    icon: ArchiveBoxIcon,
  },
  {
    name: "Orders (pro only)",
    href: "/settings/orders",
    icon: ClipboardDocumentListIcon,
  },
  // {
  //   name: "Loyalty",
  //   href: "/settings/loyalty",
  //   icon: GiftIcon,
  // },
];

export default function SettingsLayout({ children }) {
  useMenuFix();
  return (
    <>
      <div className="lg:flex lg:gap-x-16 bg-white rounded-2xl shadow-lg p-6">
        <aside className="flex border-b border-gray-900/5 lg:block lg:w-64 lg:flex-none lg:border-0 ">
          <nav className="flex-none px-4 sm:px-6 lg:px-0">
            <ul
              role="list"
              className="flex gap-x-3 gap-y-1 whitespace-nowrap lg:flex-col"
            >
              {secondaryNavigation.map((item) => (
                <li key={item.name}>
                  <Link
                    to={item.href}
                    className={classNames(
                      location.hash.replace(/^#/, "") === item.href
                        ? "bg-gray-50 text-sky-600"
                        : "text-gray-700 hover:text-sky-600 hover:bg-gray-50",
                      "group flex gap-x-3 rounded-lg py-2 pl-2 pr-3 text-sm leading-6 font-semibold"
                    )}
                  >
                    <item.icon
                      className={classNames(
                        location.hash.replace(/^#/, "") === item.href
                          ? "text-sky-600"
                          : "text-gray-400 group-hover:text-sky-600",
                        "h-6 w-6 shrink-0"
                      )}
                      aria-hidden="true"
                    />
                    {item.name}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        </aside>
        <main className="px-4 sm:px-6 lg:flex-auto lg:px-0">{children}</main>
      </div>
    </>
  );
}
