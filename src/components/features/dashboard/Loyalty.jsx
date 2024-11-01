
import { GiftIcon } from '@heroicons/react/24/outline';
import { Link } from 'react-router-dom';

export default function Loyalty({ loyalty }) {
    return (
        <section className=" bg-white rounded-xl p-5 w-full mb-6 h-full">
            <header className="flex items-center gap-2 relative w-full">
                <GiftIcon className='w-6 h-6 text-black' />
                <h3 className="text-base font-semibold leading-6 text-gray-900">
                    Loyalty Program is {loyalty && loyalty.enabled ? 'on' : 'off'}
                </h3>
                {loyalty && loyalty.enabled ? <div className="absolute top-0 right-0">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                </div> : <div className="absolute top-0 right-0">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                    </span>
                </div>}

            </header>
            <div>
                {loyalty && loyalty.enabled ?
                    <p className='text-sm text-gray-500 mt-2'>Eligible customers will earn points on orders</p> :
                    <p className="text-gray-500 mt-2">Loyalty point earning is currently disabled. To enabled, go to loyalty settings <Link to={'/settings/loyalty'} className="text-sky-500">here</Link>.</p>}
            </div>
        </section>
    );
}
