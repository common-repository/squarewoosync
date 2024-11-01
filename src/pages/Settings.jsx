import General from './settings/General';
import useMenuFix from '../components/hooks/useMenuFix';

export default function Settings() {
	useMenuFix();
	return <General />
}
