import { NavLink } from "react-router-dom";
import LogoImg from "../../../assets/images/logo.png";

// Function to generate NavLink with dynamic classes
function CustomNavLink({ to, children }) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        isActive
          ? "text-sky-400 focus:!shadow-none active:text-sky-400 focus:text-sky-400 hover:!text-sky-400"
          : "focus:!shadow-none active:text-sky-400 focus:text-sky-400 hover:!text-sky-400"
      }
    >
      {children}
    </NavLink>
  );
}

function Layout({ children }) {
  const listItemClasses = "h-full flex items-center justify-center mb-0";



  return (
    <div className="relative">
      <header className="bg-white px-10 xl:px-20 items-stretch justify-between h-14 hidden md:flex">
        <div className="flex items-center gap-px py-4 ">
          <img className="h-10 w-auto" src={LogoImg} alt="SquareWooSync" />
          <h2 className="ml-2 font-bold text-base ">SquareSync for Woocommerce</h2>
          <nav className="h-full ml-10">
            <ul className="flex items-center h-full gap-4 justify-center divide-x divide-gray-200 font-semibold ">
              <li className={listItemClasses}>
                <CustomNavLink to={"/"}>Dashboard</CustomNavLink>
              </li>
              <li className={`${listItemClasses} pl-4`}>
                <CustomNavLink to={"/inventory"}>Products</CustomNavLink>
              </li>
              <li className={`${listItemClasses} pl-4`}>
                <CustomNavLink to={"/orders"}>Orders</CustomNavLink>
              </li>
              <li className={`${listItemClasses} pl-4`}>
                <CustomNavLink to={"/settings/general"}>Settings</CustomNavLink>
              </li>
              <li className={`${listItemClasses} pl-4`}>
                <a
                  target="_blank"
                  href={"https://squaresyncforwoo.com/documentation"}
                  className={({ isActive }) =>
                    isActive
                      ? "text-sky-400 focus:!shadow-none active:text-sky-400 focus:text-sky-400 hover:!text-sky-400"
                      : "focus:!shadow-none active:text-sky-400 focus:text-sky-400 hover:!text-sky-400"
                  }
                >
                  Documentation
                </a>
              </li>
            </ul>
          </nav>
        </div>
      </header>

      <main
        className={` mx-auto pb-20 mt-10 px-10 xl:px-20`}
      >
        {children}
      </main>
    </div>
  );
}

export default Layout;
