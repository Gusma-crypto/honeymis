import { useState } from 'react';

export function MemberAvatar({
    name,
    gender,
    photo,
    className = '',
}: {
    name: string;
    gender?: string | null;
    photo?: string | null;
    className?: string;
}) {
    const [failedPhoto, setFailedPhoto] = useState<string | null>(null);
    if (photo && failedPhoto !== photo) {
        return (
            <img
                className={`member-avatar ${className}`}
                src={photo}
                alt={name}
                onError={() => setFailedPhoto(photo)}
            />
        );
    }
    const female = gender === 'female';
    const male = gender === 'male';
    return (
        <svg
            viewBox="0 0 80 80"
            role="img"
            aria-label={`Avatar kartun ${name}`}
            className={`member-avatar ${className}`}
        >
            <circle
                cx="40"
                cy="40"
                r="40"
                fill={female ? '#f4deef' : male ? '#dce9fa' : '#e3e5f5'}
            />
            {female && (
                <path d="M18 57V32C18 8 62 8 62 32V58Z" fill="#503c39" />
            )}
            <path
                d="M9 80V72C10 53 29 55 40 55S70 54 72 73V80Z"
                fill={female ? '#9272b6' : male ? '#365d91' : '#7483a8'}
            />
            <path d="M32 52V61Q40 69 48 61V51Z" fill="#eab590" />
            <ellipse cx="40" cy="34" rx="18" ry="23" fill="#f5c8a5" />
            <path
                d="M22 31C17 8 57 5 59 29C50 24 44 19 41 17C34 27 29 28 22 31Z"
                fill={female ? '#503c39' : '#3b3638'}
            />
            {female && (
                <path d="M24 29C18 39 25 54 23 60L17 54V30Z" fill="#503c39" />
            )}
            <path
                d="M29 33Q32 31 35 33M45 33Q48 31 51 33"
                fill="none"
                stroke="#5d4540"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <ellipse cx="32" cy="37" rx="1.8" ry="2.3" fill="#39333a" />
            <ellipse cx="48" cy="37" rx="1.8" ry="2.3" fill="#39333a" />
            <path
                d="M40 39L38 44H41"
                fill="none"
                stroke="#cf997d"
                strokeWidth="1.5"
                strokeLinecap="round"
            />
            <path
                d="M34 48Q40 54 46 48"
                fill="white"
                stroke="#c78379"
                strokeWidth="1.2"
                strokeLinejoin="round"
            />
            <ellipse
                cx="27"
                cy="44"
                rx="3.5"
                ry="2"
                fill="#eeaa9d"
                opacity=".65"
            />
            <ellipse
                cx="53"
                cy="44"
                rx="3.5"
                ry="2"
                fill="#eeaa9d"
                opacity=".65"
            />
            {male && (
                <path
                    d="M25 48Q28 60 40 60Q53 59 55 47Q49 55 40 55Q31 55 25 48"
                    fill="#4a3935"
                    opacity=".8"
                />
            )}
            <path d="M31 61L40 69L49 61L52 80H28Z" fill="#fff" opacity=".9" />
        </svg>
    );
}
