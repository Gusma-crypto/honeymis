import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                fillRule="evenodd"
                clipRule="evenodd"
                d="M24 2 43 13v22L24 46 5 35V13L24 2Zm-9 12v20h5v-8h8v8h5V14h-5v7h-8v-7h-5Z"
            />
        </svg>
    );
}
